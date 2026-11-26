<?php

namespace App\Services\Finance;

use App\Models\ProjectHeadSubhead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Same-ID Plot voucher editing. Preserves source IDs, purchased plot IDs and
 * existing posting batch/line IDs. Shared voucher snapshots retain before/after.
 * Line cardinality changes use lifecycle flags; old rows are archived,
 * original financial before-images preserved, never hard-deleted.
 */
final class PlotVoucherEditor
{
    private function cents(mixed $value, int $scale=2): int
    {
        $value=(string)$value;
        if (!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,'.$scale.'}))?$/D',$value,$m)) {
            throw new \DomainException('Invalid amount.');
        }
        return (int)$m[1]*(10**$scale)+(int)str_pad($m[2]??'',$scale,'0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);
    }

    private function account(int $project,int $account): void
    {
        if (!ProjectHeadSubhead::availableForPosting()->where('project_id',$project)->where('id',$account)->exists()) {
            throw new \DomainException('Account does not belong to active project.');
        }
    }

    private function locked(int $project,string $kind,int $id): array
    {
        $table=$kind==='plot_receipt'?'finance_plot_receipts':'finance_plot_purchases';
        $sourceQuery=DB::table($table)->where('project_id',$project)->where('id',$id)->lockForUpdate();
        if (Schema::hasColumn($table,'is_active')) $sourceQuery->where('is_active',1);
        if (Schema::hasColumn($table,'deleted_at')) $sourceQuery->whereNull('deleted_at');
        $source=$sourceQuery->first();
        if (!$source) throw new \DomainException('Active voucher not found in selected project.');
        if($kind==='plot_receipt' && Schema::hasTable('finance_bank_voucher_allocations')
            && DB::table('finance_bank_voucher_allocations')
                ->where('project_id',$project)
                ->where('voucher_type',TypedPlotReceiptBankCustody::POSTED)
                ->where('voucher_id',$id)->exists()){
            throw new \DomainException('Typed PPR verified bank source must use audited correction before editing.');
        }
        $linkTable=$kind==='plot_receipt'?'finance_plot_receipt_postings':'finance_plot_purchase_postings';
        $link=DB::table($linkTable.' as link')->join('finance_posting_batches as batch',
            'batch.id','=','link.posting_batch_id')
            ->where('link.source_id',$id)->where('link.source_kind',$kind)
            ->where('batch.project_id',$project)->lockForUpdate()
            ->first(['batch.id','batch.request_key']);
        if (!$link) throw new \DomainException('Source posting link not found.');
        if (DB::table('finance_posting_batches')->where('reversal_of_batch_id',$link->id)->exists()) {
            throw new \DomainException('Reversed voucher cannot be edited.');
        }
        $linesQuery=DB::table('finance_posting_lines')->where('posting_batch_id',$link->id);
        if (Schema::hasColumn('finance_posting_lines','is_active')) $linesQuery->where('is_active',1);
        $lines=$linesQuery->orderBy('id')->lockForUpdate()->get()
            ->sortBy(fn($line)=>(float)$line->debit>0?0:1)->values();
        return [$source,$link,$lines];
    }

    private function snapshot(int $project,string $kind,int $id,int $actor,array $before,array $after): void
    {
        if (!Schema::hasTable('daily_voucher_edit_snapshots')) {
            throw new \DomainException('Existing shared voucher history is not installed.');
        }
        DB::table('daily_voucher_edit_snapshots')->insert([
            'project_id'=>$project,'source_id'=>$id,'approval_id'=>null,'status'=>'plot_edit',
            'before_snapshot'=>json_encode(['source_kind'=>$kind]+$before,JSON_THROW_ON_ERROR),
            'proposed_snapshot'=>json_encode(['source_kind'=>$kind]+$after,JSON_THROW_ON_ERROR),
            'reason'=>'Plot voucher full form edited','submitted_by'=>$actor,'decided_by'=>$actor,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    private function syncAccountingLedgers(int $project,int $batchId,int $sourceId,string $series): void
    {
        if(!Schema::hasTable('ledger_source_attributions')||!Schema::hasTable('ledgers')){
            if(app()->environment('testing'))return;
            throw new \DomainException('GENERAL_LEDGER_SCHEMA_REQUIRED');
        }
        $table=$series==='PPR'?'finance_plot_receipts':'finance_plot_purchases';
        $numberColumn=$series==='PPR'?'receipt_number':'purchase_number';
        $reference=DB::table($table)->where('project_id',$project)
            ->where('id',$sourceId)->value($numberColumn);
        if(!$reference)throw new \DomainException('PLOT_LEDGER_SOURCE_NOT_FOUND');
        $typed=DB::table('finance_posting_lines')->where('posting_batch_id',$batchId)
            ->whereNotNull('legacy_ledger_id')->lockForUpdate()->get();
        foreach($typed as $line){
            $ledger=DB::table('ledgers')->where('id',$line->legacy_ledger_id)
                ->where('type',$series)->where('type_id',$sourceId)
                ->lockForUpdate()->first();
            if(!$ledger||!DB::table('project_head_subheads')
                ->where('id',$ledger->project_head_subheads_id)
                ->where('project_id',$project)->exists()){
                throw new \DomainException('PLOT_LEDGER_SOURCE_RELATION_INVALID');
            }
            $active=(int)($line->is_active??1)===1;
            $update=['is_active'=>$active?1:0,'reference'=>$reference,
                'updated_at'=>now()];
            if($active){
                $this->account($project,(int)$line->project_head_subhead_id);
                $update+=['project_head_subheads_id'=>$line->project_head_subhead_id,
                    'amount_in'=>$line->debit,'amount_out'=>$line->credit,
                    'detail'=>$line->description,'date'=>DB::table('finance_posting_batches')
                        ->where('id',$batchId)->where('project_id',$project)
                        ->value('effective_date')];
            }
            DB::table('ledgers')->where('id',$ledger->id)->update($update);
        }
        app(TypedLedgerBackfill::class)->inspect($project,$batchId,true);
    }

    private function replaceLines(int $project,int $batchId,$old,array $new,array $oldLegKeys=[],array $newLegKeys=[]): void
    {
        $debit=0;$credit=0;
        foreach ($new as $line) {
            $this->account($project,(int)$line['account']);
            $debit+=$this->cents($line['debit']);$credit+=$this->cents($line['credit']);
        }
        if ($debit<1 || $debit!==$credit) throw new \DomainException('Unbalanced posting rejected.');
        $hasLifecycle=Schema::hasColumn('finance_posting_lines','is_active');
        if (!$hasLifecycle && $old->count()!==count($new)) {
            throw new \DomainException('Apply Plot line lifecycle migration before changing line count.');
        }
        // Keep the existing cash/seller leg ID and match debit-to-debit and
        // credit-to-credit. New legs append to same original posting batch.
        foreach (['debit','credit'] as $side) {
            $prior=$old->filter(fn($line)=>(float)$line->$side>0)->values();
            $next=collect($new)->filter(fn($line)=>(float)$line[$side]>0)->values();
            // Match legs by their original source identity, never their position
            // in a reordered form. A removed source leaves its line archived;
            // genuinely new source identities receive a new line.
            $beforeKeys=$oldLegKeys[$side]??range(0,$prior->count()-1);
            $afterKeys=$newLegKeys[$side]??range(0,$next->count()-1);
            if (count($beforeKeys)!==$prior->count() || count($afterKeys)!==$next->count()
                || count(array_unique($beforeKeys))!==count($beforeKeys)
                || count(array_unique($afterKeys))!==count($afterKeys)) {
                throw new \DomainException('Posting source identity mismatch.');
            }
            $priorByKey=[];
            foreach ($prior as $i=>$line) $priorByKey[(string)$beforeKeys[$i]]=$line;
            $nextByKey=[];
            foreach ($next as $i=>$line) $nextByKey[(string)$afterKeys[$i]]=$line;
            foreach (array_unique(array_merge($beforeKeys,$afterKeys)) as $key) {
                $line=$priorByKey[(string)$key]??null;
                $target=$nextByKey[(string)$key]??null;
                if ($line && (int)$line->posting_batch_id!==$batchId) {
                    throw new \DomainException('Posting identity mismatch.');
                }
                if ($line && $target) {
                    DB::table('finance_posting_lines')->where('id',$line->id)
                        ->where('posting_batch_id',$batchId)
                        ->update(['project_head_subhead_id'=>$target['account'],
                            'debit'=>$target['debit'],'credit'=>$target['credit'],
                            'description'=>$target['description'],'updated_at'=>now()]);
                } elseif ($line) {
                    // Financial contribution is zeroed, never physically
                    // deleted; full previous values remain in the snapshot.
                    DB::table('finance_posting_lines')->where('id',$line->id)
                        ->where('posting_batch_id',$batchId)
                        ->update(['is_active'=>0,'updated_at'=>now()]);
                } elseif ($target) {
                    DB::table('finance_posting_lines')->insert([
                        'posting_batch_id'=>$batchId,'project_head_subhead_id'=>$target['account'],
                        'debit'=>$target['debit'],'credit'=>$target['credit'],
                        'description'=>$target['description'],'is_active'=>1,
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                }
            }
        }
    }

    public function receipt(int $project,int $id,array $data,int $actor): array
    {
        FinanceConnectionGuard::assertAllowed();
        if ($project<1 || $id<1 || $actor<1) throw new \DomainException('Invalid source.');
        return DB::transaction(function()use($project,$id,$data,$actor){
            [$source,$batch,$lines]=$this->locked($project,'plot_receipt',$id);
            $allocQuery=DB::table('finance_plot_receipt_allocations')->where('receipt_id',$id);
            if (Schema::hasColumn('finance_plot_receipt_allocations','is_active')) $allocQuery->where('is_active',1);
            $old=$allocQuery->orderBy('id')->lockForUpdate()->get();
            if ($lines->count()!==$old->count()+1) {
                throw new \DomainException('Existing Plot Receipt posting does not match active allocations.');
            }
            // Resolve original credit legs by their related account/plot and
            // exact amount, never by old posting-line row order. A previously
            // restored booking can have an older allocation ID but a newer
            // active posting-line ID.
            $priorCredits=$lines->filter(fn($line)=>(float)$line->credit>0)->values();
            $bookingByLineId=[];
            foreach ($old as $allocation) {
                $matches=$priorCredits->filter(function($leg)use($allocation,$project,$source,$bookingByLineId) {
                    return !isset($bookingByLineId[$leg->id])
                        && $this->cents($leg->credit)===$this->cents($allocation->amount)
                        && DB::table('project_head_subheads')
                            ->where('id',$leg->project_head_subhead_id)->where('project_id',$project)
                            ->where('customer_id',$source->customer_id)
                            ->where('plot_id',$allocation->plot_id)->exists();
                })->values();
                if ($matches->count()!==1) {
                    throw new \DomainException('Existing receipt allocation posting relationship mismatch.');
                }
                $bookingByLineId[(int)$matches[0]->id]=(string)$allocation->booking_id;
            }
            $oldBookingIds=$old->pluck('booking_id')->map(fn($id)=>(int)$id)->sort()->values()->all();
            $newBookingIds=array_map(static fn(array $row): int => (int)$row['booking_id'],$data['allocations']);
            sort($newBookingIds);
            if (!Schema::hasColumn('finance_plot_receipt_allocations','is_active')
                && $oldBookingIds!==$newBookingIds) {
                throw new \DomainException('Apply Plot line lifecycle migration before changing allocation bookings.');
            }
            $customer=(int)$data['customer_id'];$cash=(int)$data['cash_account_id'];
            if (!DB::table('leads')->where('id',$customer)->where('project_id',$project)->exists())
                throw new \DomainException('Customer project mismatch.');
            $this->account($project,$cash);
            $number=trim((string)$data['receipt_number']);
            if (DB::table('finance_plot_receipts')->where('project_id',$project)
                ->where('receipt_number',$number)->where('id','!=',$id)->exists())
                throw new \DomainException('Voucher number already exists.');
            $expected=$this->cents($data['amount']);$total=0;$seen=[];
            $newLines=[['account'=>$cash,'debit'=>$this->money($expected),'credit'=>'0.00',
                'description'=>$data['narration']]];
            $newAllocations=[];
            foreach ($data['allocations'] as $a) {
                $booking=(int)$a['booking_id'];$plot=(int)$a['plot_id'];$account=(int)$a['receivable_account_id'];
                $amount=$this->cents($a['amount']);
                if ($amount<1 || isset($seen[$plot]) || isset($seen['booking:'.$booking])) {
                    throw new \DomainException('Duplicate booking/plot or zero plot allocation.');
                }
                $seen[$plot]=true;
                $seen['booking:'.$booking]=true;
                if (!DB::table('bookings')->where('id',$booking)->where('project_id',$project)
                    ->where('customer_id',$customer)->where('plot_id',$plot)
                    ->where('status','active')->where('cancel_status','0')->lockForUpdate()->exists())
                    throw new \DomainException('Booking is not active in selected project.');
                $this->account($project,$account);
                if ($cash===$account || !DB::table('project_head_subheads')
                    ->where('id',$account)->where('project_id',$project)->where('customer_id',$customer)
                    ->where('plot_id',$plot)->exists()) throw new \DomainException('Receivable account mismatch.');
                $total+=$amount;
                $newAllocations[]=['booking_id'=>$booking,'plot_id'=>$plot,'amount'=>$this->money($amount)];
                $newLines[]=['account'=>$account,'debit'=>'0.00','credit'=>$this->money($amount),
                    'description'=>$data['narration']];
            }
            if ($total!==$expected) throw new \DomainException('Allocated amount does not equal voucher total.');
            $before=['voucher'=>(array)$source,'allocations'=>$old->map(fn($r)=>(array)$r)->all(),
                'posting_lines'=>$lines->map(fn($r)=>(array)$r)->all()];
            $this->replaceLines($project,(int)$batch->id,$lines,$newLines,
                ['debit'=>['receipt_cash'],'credit'=>$priorCredits->map(fn($line)=>$bookingByLineId[(int)$line->id])->all()],
                ['debit'=>['receipt_cash'],'credit'=>array_map(fn($value)=>(string)$value['booking_id'],$newAllocations)]);
            // (receipt_id, booking_id) is UNIQUE. Never recycle a row
            // position for another booking: identity is the booking itself.
            // Include inactive rows so a previously removed booking can be
            // reactivated instead of attempting an illegal duplicate insert.
            $allRows=DB::table('finance_plot_receipt_allocations')
                ->where('receipt_id',$id)->orderBy('id')->lockForUpdate()->get();
            $byBooking=$allRows->keyBy('booking_id');
            $desiredBookings=array_fill_keys(
                array_map(static fn(array $a): int => (int)$a['booking_id'],$newAllocations),true
            );
            foreach ($old as $row) {
                if (!isset($desiredBookings[(int)$row->booking_id])) {
                    DB::table('finance_plot_receipt_allocations')->where('id',$row->id)
                        ->where('receipt_id',$id)->update(['is_active'=>0,'updated_at'=>now()]);
                }
            }
            foreach ($newAllocations as $allocation) {
                $existing=$byBooking->get((int)$allocation['booking_id']);
                if ($existing) {
                    $values=$allocation+['updated_at'=>now()];
                    if (Schema::hasColumn('finance_plot_receipt_allocations','is_active')) {
                        $values['is_active']=1;
                    }
                    DB::table('finance_plot_receipt_allocations')->where('id',$existing->id)
                        ->where('receipt_id',$id)->update($values);
                } else {
                    $values=$allocation+['receipt_id'=>$id,
                        'created_at'=>now(),'updated_at'=>now()];
                    if (Schema::hasColumn('finance_plot_receipt_allocations','is_active')) {
                        $values['is_active']=1;
                    }
                    DB::table('finance_plot_receipt_allocations')->insert($values);
                }
            }
            DB::table('finance_plot_receipts')->where('id',$id)->where('project_id',$project)->update([
                'receipt_number'=>$number,'receipt_date'=>$data['date'],'customer_id'=>$customer,
                'amount'=>$this->money($expected),'narration'=>$data['narration'],'updated_at'=>now(),
            ]);
            DB::table('finance_posting_batches')->where('id',$batch->id)->where('project_id',$project)
                ->update(['effective_date'=>$data['date'],'updated_at'=>now()]);
            $this->syncAccountingLedgers($project,(int)$batch->id,$id,'PPR');
            $this->snapshot($project,'plot_receipt',$id,$actor,$before,[
                'voucher'=>$data,'posting_batch_id'=>(int)$batch->id]);
            return ['source_id'=>$id,'posting_batch_id'=>(int)$batch->id,'updated'=>true];
        },3);
    }

    public function purchase(int $project,int $id,array $data,int $actor): array
    {
        FinanceConnectionGuard::assertAllowed();
        if ($project<1 || $id<1 || $actor<1) throw new \DomainException('Invalid source.');
        return DB::transaction(function()use($project,$id,$data,$actor){
            [$source,$batch,$lines]=$this->locked($project,'plot_purchase',$id);
            $itemQuery=DB::table('finance_plot_purchase_items')->where('purchase_id',$id);
            if (Schema::hasColumn('finance_plot_purchase_items','is_active')) $itemQuery->where('is_active',1);
            $old=$itemQuery->orderBy('id')->lockForUpdate()->get();
            if ($lines->count()!==$old->count()+1) {
                throw new \DomainException('Purchase posting does not match active purchased plots.');
            }
            if ($old->count()!==count($data['items'])
                && !Schema::hasColumn('finance_plot_purchase_items','is_active')) {
                throw new \DomainException('Apply Plot line lifecycle migration before adding/removing purchased plots.');
            }
            // Match inventory legs to purchased item source identity using
            // unique plot code in their persisted description, account and
            // amount. Do not assume posting-line IDs sort like item IDs.
            $priorDebits=$lines->filter(fn($line)=>(float)$line->debit>0)->values();
            $plotByLineId=[];
            foreach ($old as $item) {
                $expectedDescription='Plot purchase '.$item->plot_code.' · '.$item->description;
                $matches=$priorDebits->filter(fn($leg)=>
                    !isset($plotByLineId[(int)$leg->id])
                    && (int)$leg->project_head_subhead_id===(int)$item->inventory_account_id
                    && $this->cents($leg->debit)===$this->cents($item->amount)
                    && (string)$leg->description===$expectedDescription)->values();
                if ($matches->count()!==1 || !DB::table('plots')
                    ->where('id',$item->legacy_plot_id)->where('project_id',$project)->exists()) {
                    throw new \DomainException('Existing purchase inventory posting relationship mismatch.');
                }
                $plotByLineId[(int)$matches[0]->id]=(string)$item->legacy_plot_id;
            }
            $seller=(int)$data['seller_account_id'];$this->account($project,$seller);
            $number=trim((string)$data['purchase_number']);
            if (DB::table('finance_plot_purchases')->where('project_id',$project)
                ->where('purchase_number',$number)->where('id','!=',$id)->exists()) {
                throw new \DomainException('Voucher number already exists.');
            }
            $byPlot=$old->keyBy('legacy_plot_id');
            $plots=[];$total=0;$newLines=[];$usedCodes=[];$usedIds=[];
            foreach ($data['items'] as $index=>$item) {
                // Missing identity is a new row. Never silently assign an
                // existing plot by its old array index after form reordering.
                $legacyId=(int)($item['legacy_plot_id']??0);
                if ($legacyId && (isset($usedIds[$legacyId]) || !$byPlot->has($legacyId))) {
                    throw new \DomainException('Purchased plot is not linked to this source.');
                }
                if ($legacyId) $usedIds[$legacyId]=true;
                $row=$legacyId?$byPlot[$legacyId]:null;
                $plot=$row ? DB::table('plots')->where('id',$legacyId)
                    ->where('project_id',$project)->lockForUpdate()->first() : null;
                if ($row && (!$plot || (int)$plot->sold!==0 || (int)$plot->is_active!==1
                    || DB::table('bookings')->where('plot_id',$plot->id)->exists())) {
                    throw new \DomainException('Purchased plot has bookings or unavailable inventory.');
                }
                $code=trim((string)$item['plot_code']);
                if ($code==='' || isset($usedCodes[strtolower($code)])
                    || DB::table('plots')->where('project_id',$project)->where('name',$code)
                        ->when($plot,fn($q)=>$q->where('id','!=',$plot->id))->exists()) {
                    throw new \DomainException('Plot code already exists.');
                }
                $usedCodes[strtolower($code)]=true;
                if (!DB::table('roads')->where('id',$item['road_id'])->where('is_active',1)->exists()
                    || !DB::table('facings')->where('id',$item['facing_id'])->where('is_active',1)->exists()) {
                    throw new \DomainException('Invalid road or facing.');
                }
                $account=(int)$item['inventory_account_id'];$this->account($project,$account);
                $area=$this->cents($item['area'],4);$rate=$this->cents($item['unit_rate']);
                if ($area<1 || $rate<1 || $area>intdiv(PHP_INT_MAX,$rate)) {
                    throw new \DomainException('Invalid plot area or rate.');
                }
                $amount=(int)round($area*$rate/10000,0,PHP_ROUND_HALF_UP);
                if ($amount<1 || $total>PHP_INT_MAX-$amount) {
                    throw new \DomainException('Invalid plot purchase amount.');
                }
                $total+=$amount;
                $plots[]=['row'=>$row,'plot'=>$plot,'input'=>$item,'amount'=>$this->money($amount)];
                $newLines[]=['account'=>$account,'debit'=>$this->money($amount),'credit'=>'0.00',
                    'description'=>'Plot purchase '.$code.' · '.$item['description']];
            }
            $removed=$old->filter(fn($item)=>!isset($usedIds[(int)$item->legacy_plot_id]))->values();
            foreach ($removed as $row) {
                $plot=DB::table('plots')->where('id',$row->legacy_plot_id)
                    ->where('project_id',$project)->lockForUpdate()->first();
                if (!$plot || (int)$plot->sold!==0 || (int)$plot->is_active!==1
                    || DB::table('bookings')->where('plot_id',$plot->id)->exists()) {
                    throw new \DomainException('Cannot remove booked/sold purchased plot.');
                }
            }
            $newLines[]=['account'=>$seller,'debit'=>'0.00','credit'=>$this->money($total),
                'description'=>$data['narration']];
            $priorPlots=DB::table('plots')->whereIn('id',$old->pluck('legacy_plot_id')->all())
                ->where('project_id',$project)->get()->map(fn($r)=>(array)$r)->all();
            $before=['voucher'=>(array)$source,'items'=>$old->map(fn($r)=>(array)$r)->all(),
                'posting_lines'=>$lines->map(fn($r)=>(array)$r)->all(),'plots'=>$priorPlots];
            $this->replaceLines($project,(int)$batch->id,$lines,$newLines,
                ['debit'=>$priorDebits->map(fn($line)=>$plotByLineId[(int)$line->id])->all(),'credit'=>['purchase_seller']],
                ['debit'=>array_map(fn($index,$value)=>(string)($value['plot']?->id ?? 'new:'.$index),array_keys($plots),$plots),'credit'=>['purchase_seller']]);
            foreach ($plots as $p) {
                $v=$p['input'];$existing=$p['plot'];
                if ($existing) {
                    $plotId=(int)$existing->id;
                    $changed=['name'=>trim($v['plot_code']),'description'=>$v['description'],
                        'size'=>$v['area'],'unit'=>$v['unit'],'type'=>(string)$v['type'],
                        'road_id'=>$v['road_id'],'facing_id'=>$v['facing_id'],
                        'is_corner'=>($v['is_corner']??0)?1:0];
                    DB::table('plots')->where('id',$plotId)->where('project_id',$project)->update($changed);
                    DB::table('finance_plot_purchase_items')->where('id',$p['row']->id)
                        ->where('purchase_id',$id)->update([
                            'plot_code'=>trim($v['plot_code']),'description'=>$v['description'],
                            'area'=>$v['area'],'unit'=>$v['unit'],'unit_rate'=>$v['unit_rate'],
                            'amount'=>$p['amount'],'inventory_account_id'=>$v['inventory_account_id'],
                            'updated_at'=>now(),
                        ]);
                } else {
                    $plotId=(new PlotEntryWriter())->createAvailablePlot($project,$v,$actor);
                    DB::table('finance_plot_purchase_items')->insert([
                        'purchase_id'=>$id,'legacy_plot_id'=>$plotId,
                        'plot_code'=>trim($v['plot_code']),'description'=>$v['description'],
                        'area'=>$v['area'],'unit'=>$v['unit'],'unit_rate'=>$v['unit_rate'],
                        'amount'=>$p['amount'],'inventory_account_id'=>$v['inventory_account_id'],
                        'is_active'=>1,'created_at'=>now(),'updated_at'=>now(),
                    ]);
                }
            }
            foreach ($removed as $row) {
                DB::table('finance_plot_purchase_items')->where('purchase_id',$id)
                    ->where('id',$row->id)->update(['is_active'=>0,'updated_at'=>now()]);
                DB::table('plots')->where('id',$row->legacy_plot_id)->where('project_id',$project)
                    ->update(['is_active'=>0,'updated_at'=>now()]);
            }
            DB::table('finance_plot_purchases')->where('id',$id)->where('project_id',$project)->update([
                'purchase_number'=>$number,'purchase_date'=>$data['date'],'seller_account_id'=>$seller,
                'amount'=>$this->money($total),'narration'=>$data['narration'],'updated_at'=>now(),
            ]);
            DB::table('finance_posting_batches')->where('id',$batch->id)->where('project_id',$project)
                ->update(['effective_date'=>$data['date'],'updated_at'=>now()]);
            $this->syncAccountingLedgers($project,(int)$batch->id,$id,'PP');
            $this->snapshot($project,'plot_purchase',$id,$actor,$before,[
                'voucher'=>$data,'posting_batch_id'=>(int)$batch->id,'amount'=>$this->money($total)]);
            return ['source_id'=>$id,'posting_batch_id'=>(int)$batch->id,'updated'=>true];
        },3);
    }

}
