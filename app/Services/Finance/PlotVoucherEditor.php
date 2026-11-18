<?php

namespace App\Services\Finance;

use App\Models\ProjectHeadSubhead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Same-ID Plot voucher editing. Preserves source IDs, purchased plot IDs and
 * existing posting batch/line IDs. Shared voucher snapshots retain before/after.
 * Item/plot allocation cardinality is fixed until soft-deletable line lifecycle
 * is available; never physically delete finance evidence.
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
        $lines=DB::table('finance_posting_lines')->where('posting_batch_id',$link->id)
            ->orderBy('id')->lockForUpdate()->get();
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

    private function replaceLines(int $project,int $batchId,$old,array $new): void
    {
        if ($old->count()!==count($new)) {
            throw new \DomainException('Changing plot/allocations count requires line lifecycle support; existing links were not changed.');
        }
        $debit=0;$credit=0;
        foreach ($new as $line) {
            $this->account($project,(int)$line['account']);
            $debit+=$this->cents($line['debit']);$credit+=$this->cents($line['credit']);
        }
        if ($debit<1 || $debit!==$credit) throw new \DomainException('Unbalanced posting rejected.');
        foreach ($old->values() as $index=>$line) {
            if ((int)$line->posting_batch_id!==$batchId) throw new \DomainException('Posting identity mismatch.');
            DB::table('finance_posting_lines')->where('id',$line->id)->where('posting_batch_id',$batchId)
                ->update(['project_head_subhead_id'=>$new[$index]['account'],
                    'debit'=>$new[$index]['debit'],'credit'=>$new[$index]['credit'],
                    'description'=>$new[$index]['description'],'updated_at'=>now()]);
        }
    }

    public function receipt(int $project,int $id,array $data,int $actor): array
    {
        FinanceConnectionGuard::assertAllowed();
        if ($project<1 || $id<1 || $actor<1) throw new \DomainException('Invalid source.');
        return DB::transaction(function()use($project,$id,$data,$actor){
            [$source,$batch,$lines]=$this->locked($project,'plot_receipt',$id);
            $old=DB::table('finance_plot_receipt_allocations')->where('receipt_id',$id)
                ->orderBy('id')->lockForUpdate()->get();
            if ($old->count()!==count($data['allocations']) || $lines->count()!==$old->count()+1) {
                throw new \DomainException('Plot allocation count cannot change in this edit.');
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
                if ($amount<1 || isset($seen[$plot])) throw new \DomainException('Duplicate or zero plot allocation.');
                $seen[$plot]=true;
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
            $this->replaceLines($project,(int)$batch->id,$lines,$newLines);
            foreach ($old->values() as $index=>$row) {
                DB::table('finance_plot_receipt_allocations')->where('id',$row->id)
                    ->where('receipt_id',$id)->update($newAllocations[$index]+['updated_at'=>now()]);
            }
            DB::table('finance_plot_receipts')->where('id',$id)->where('project_id',$project)->update([
                'receipt_number'=>$number,'receipt_date'=>$data['date'],'customer_id'=>$customer,
                'amount'=>$this->money($expected),'narration'=>$data['narration'],'updated_at'=>now(),
            ]);
            DB::table('finance_posting_batches')->where('id',$batch->id)->where('project_id',$project)
                ->update(['effective_date'=>$data['date'],'updated_at'=>now()]);
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
            $old=DB::table('finance_plot_purchase_items')->where('purchase_id',$id)
                ->orderBy('id')->lockForUpdate()->get();
            if ($old->count()!==count($data['items']) || $lines->count()!==$old->count()+1)
                throw new \DomainException('Purchased plot count cannot change in this edit.');
            $seller=(int)$data['seller_account_id'];$this->account($project,$seller);
            $number=trim((string)$data['purchase_number']);
            if (DB::table('finance_plot_purchases')->where('project_id',$project)
                ->where('purchase_number',$number)->where('id','!=',$id)->exists())
                throw new \DomainException('Voucher number already exists.');
            $plots=[];$total=0;$newLines=[];$used=[];
            foreach ($data['items'] as $i=>$item) {
                $row=$old[$i];$plot=DB::table('plots')->where('id',$row->legacy_plot_id)
                    ->where('project_id',$project)->lockForUpdate()->first();
                if (!$plot || (int)$plot->sold!==0 || (int)$plot->is_active!==1
                    || DB::table('bookings')->where('plot_id',$plot->id)->exists())
                    throw new \DomainException('Purchased plot has bookings or unavailable inventory.');
                $code=trim((string)$item['plot_code']);
                if ($code==='' || isset($used[strtolower($code)])
                    || DB::table('plots')->where('project_id',$project)->where('name',$code)
                        ->where('id','!=',$plot->id)->exists())
                    throw new \DomainException('Plot code already exists.');
                $used[strtolower($code)]=true;
                if (!DB::table('roads')->where('id',$item['road_id'])->where('is_active',1)->exists()
                    || !DB::table('facings')->where('id',$item['facing_id'])->where('is_active',1)->exists())
                    throw new \DomainException('Invalid road or facing.');
                $account=(int)$item['inventory_account_id'];$this->account($project,$account);
                $area=$this->cents($item['area'],4);$rate=$this->cents($item['unit_rate']);
                if ($area<1 || $rate<1 || $area>intdiv(PHP_INT_MAX,$rate)) throw new \DomainException('Invalid plot area or rate.');
                $amount=(int)round($area*$rate/10000,0,PHP_ROUND_HALF_UP);
                if ($amount<1 || $total>PHP_INT_MAX-$amount) throw new \DomainException('Invalid plot purchase amount.');
                $total+=$amount;
                $plots[]=['row'=>$row,'plot'=>$plot,'input'=>$item,'amount'=>$this->money($amount)];
                $newLines[]=['account'=>$account,'debit'=>$this->money($amount),'credit'=>'0.00',
                    'description'=>'Plot purchase '.$code.' · '.$item['description']];
            }
            $newLines[]=['account'=>$seller,'debit'=>'0.00','credit'=>$this->money($total),
                'description'=>$data['narration']];
            $before=['voucher'=>(array)$source,'items'=>$old->map(fn($r)=>(array)$r)->all(),
                'posting_lines'=>$lines->map(fn($r)=>(array)$r)->all(),
                'plots'=>array_map(fn($p)=>(array)$p['plot'],$plots)];
            $this->replaceLines($project,(int)$batch->id,$lines,$newLines);
            foreach ($plots as $p) {
                $v=$p['input'];$idPlot=(int)$p['plot']->id;
                $changed=['name'=>trim($v['plot_code']),'description'=>$v['description'],
                    'size'=>$v['area'],'unit'=>$v['unit'],'type'=>(string)$v['type'],
                    'road_id'=>$v['road_id'],'facing_id'=>$v['facing_id'],
                    'is_corner'=>($v['is_corner']??0)?1:0];
                foreach (['category','type','size','unit'] as $type) {
                    $field='plot_'.$type.'_definition_id';
                    if (!Schema::hasColumn('plots',$field)) continue;
                    $definition=(int)($v[$field]??0);
                    if ($definition && (!Schema::hasTable('plot_definitions') || !DB::table('plot_definitions')
                        ->where('id',$definition)->where('project_id',$project)
                        ->where('kind',$type)->where('is_active',1)->exists()))
                        throw new \DomainException('Invalid plot definition.');
                    $changed[$field]=$definition?:null;
                }
                DB::table('plots')->where('id',$idPlot)->where('project_id',$project)->update($changed);
                DB::table('finance_plot_purchase_items')->where('id',$p['row']->id)->where('purchase_id',$id)->update([
                    'plot_code'=>trim($v['plot_code']),'description'=>$v['description'],'area'=>$v['area'],
                    'unit'=>$v['unit'],'unit_rate'=>$v['unit_rate'],'amount'=>$p['amount'],
                    'inventory_account_id'=>$v['inventory_account_id'],'updated_at'=>now(),
                ]);
            }
            DB::table('finance_plot_purchases')->where('id',$id)->where('project_id',$project)->update([
                'purchase_number'=>$number,'purchase_date'=>$data['date'],'seller_account_id'=>$seller,
                'amount'=>$this->money($total),'narration'=>$data['narration'],'updated_at'=>now(),
            ]);
            DB::table('finance_posting_batches')->where('id',$batch->id)->where('project_id',$project)
                ->update(['effective_date'=>$data['date'],'updated_at'=>now()]);
            $this->snapshot($project,'plot_purchase',$id,$actor,$before,[
                'voucher'=>$data,'posting_batch_id'=>(int)$batch->id,'amount'=>$this->money($total)]);
            return ['source_id'=>$id,'posting_batch_id'=>(int)$batch->id,'updated'=>true];
        },3);
    }
}
