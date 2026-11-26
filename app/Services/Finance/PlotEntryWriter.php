<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Isolated, append-only plot receipts and plot purchase posting. */
final class PlotEntryWriter
{
    private function cents(mixed $value, int $scale = 2): int
    {
        $s = (string) $value;
        if (!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,'. $scale .'}))?$/D', $s, $m)) {
            throw new \DomainException('V2_PLOT_AMOUNT_INVALID');
        }
        return (int) $m[1] * (10 ** $scale) + (int) str_pad($m[2] ?? '', $scale, '0');
    }
    private function money(int $cents): string
    { return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT); }
    private function date(string $value): void
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new \DomainException('V2_PLOT_DATE_INVALID');
    }
    private function projectAccount(int $project, int $account): bool
    { return \App\Models\ProjectHeadSubhead::availableForPosting()->where('project_id',$project)->where('id',$account)->exists(); }

    public function createAvailablePlot(int $project, array $item, ?int $actor): int
    {
        $type = (int) $item['type'];
        $road = DB::table('roads')->where('id', (int) $item['road_id'])->where('is_active', 1)->exists();
        $facing = DB::table('facings')->where('id', (int) $item['facing_id'])->where('is_active', 1)->exists();
        if (!$road || !$facing || !in_array($type, [1, 2, 3], true)) {
            throw new \DomainException('V2_PLOT_PURCHASE_DEFINITION_INVALID');
        }
        $name = trim((string) $item['plot_code']);
        if (DB::table('plots')->where('project_id', $project)->where('name', $name)->lockForUpdate()->exists()) {
            throw new \DomainException('V2_PLOT_CODE_ALREADY_EXISTS');
        }

        $plot = [
            'name' => $name, 'type' => (string) $type, 'size' => (string) $item['area'], 'unit' => (string) $item['unit'],
            'description' => trim((string) $item['description']), 'is_corner' => (int) ($item['is_corner'] ?? 0),
            'project_id' => $project, 'road_id' => (int) $item['road_id'], 'facing_id' => (int) $item['facing_id'],
            'is_active' => 1, 'create_by' => $actor,
        ];
        if (Schema::hasColumn('plots', 'sold')) $plot['sold'] = 0;
        // Purchase cost belongs to the purchase source and inventory posting;
        // never copy it into the legacy sale-price field.
        if (Schema::hasColumn('plots', 'amount')) $plot['amount'] = '0.00';
        if (Schema::hasColumn('plots', 'plot_history')) $plot['plot_history'] = null;
        return (int) DB::table('plots')->insertGetId($plot);
    }
    private function existingReplay(int $project, string $key, string $kind, string $hash): ?array
    {
        $batch = DB::table('finance_posting_batches')->where('project_id',$project)->where('request_key',$key)->lockForUpdate()->first();
        if (!$batch) return null;
        $sourceTable = $kind === 'plot_receipt' ? 'finance_plot_receipt_postings' : 'finance_plot_purchase_postings';
        $link = DB::table($sourceTable)->where('posting_batch_id',$batch->id)->first();
        if (!$link || $batch->voucher_series !== ($kind === 'plot_receipt' ? 'PR' : 'PP')
            || !hash_equals((string)$batch->payload_sha256,$hash)) throw new \DomainException('V2_PLOT_REQUEST_CONFLICT');
        // Do not revive an archived source via an old idempotency key.
        // This check is a read-only lifecycle guard, not a new posting.
        $sourceRoot = $kind === 'plot_receipt' ? 'finance_plot_receipts' : 'finance_plot_purchases';
        $sourceQuery = DB::table($sourceRoot)->where('id',(int)$link->source_id)
            ->where('project_id',$project);
        if (Schema::hasColumn($sourceRoot,'is_active')) $sourceQuery->where('is_active',1);
        if (Schema::hasColumn($sourceRoot,'deleted_at')) $sourceQuery->whereNull('deleted_at');
        if (!$sourceQuery->exists()) throw new \DomainException('PLOT_SOURCE_ARCHIVED');
        // A former Create idempotency key must not replay the outdated payload
        // after full same-source Edit. Snapshot status is distinct from notes.
        if (Schema::hasTable('daily_voucher_edit_snapshots')
            && DB::table('daily_voucher_edit_snapshots')
                ->where('project_id',$project)->where('source_id',(int)$link->source_id)
                ->where('status','plot_edit')
                ->where('before_snapshot','like','%"source_kind":"'.$kind.'"%')->exists()) {
            throw new \DomainException('PLOT_SOURCE_HAS_BEEN_EDITED');
        }
        PostedVoucherLedgerGate::assertLinked($project, (int) $batch->id);
        return ['source_id'=>(int)$link->source_id,'posting_batch_id'=>(int)$batch->id,'replayed'=>true];
    }
    private function post(int $project, string $key, string $series, string $kind, string $date, array $lines, string $payloadHash, ?int $actor, int $voucherNumber): int
    {
        $debit=0; $credit=0;
        foreach ($lines as $line) { $debit += $this->cents($line['debit']); $credit += $this->cents($line['credit']); }
        if ($debit < 1 || $debit !== $credit) throw new \DomainException('V2_PLOT_POSTING_UNBALANCED');
        $batch=DB::table('finance_posting_batches')->insertGetId([
            'project_id'=>$project,'voucher_series'=>$series,'voucher_number'=>$voucherNumber,'request_key'=>$key,
            'payload_sha256'=>$payloadHash,'effective_date'=>$date,'posted_by'=>$actor,'posted_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('finance_posting_source_claims')->insert(['posting_batch_id'=>$batch,'source_kind'=>$kind]);
        foreach ($lines as $line) DB::table('finance_posting_lines')->insert([
            'posting_batch_id'=>$batch,'project_head_subhead_id'=>$line['account_id'],'debit'=>$line['debit'],
            'credit'=>$line['credit'],'description'=>$line['description'] ?? null,'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $batch;
    }

    /**
     * Protect reserved pending voucher numbers under the same project row lock
     * used by direct and approval postings. An approver may use only their
     * exact locked pending draft; a direct writer cannot consume it.
     */
    private function assertApprovalReservation(int $project,string $type,string $number,?int $draftId): void
    {
        if(!Schema::hasTable('finance_plot_approval_drafts')){
            if($draftId!==null)throw new \DomainException('PLOT_APPROVAL_DRAFT_MIGRATION_REQUIRED');
            return;
        }
        $reservation=DB::table('finance_plot_approval_drafts')
            ->where('project_id',$project)->where('voucher_type',$type)
            ->where('source_number',$number)->lockForUpdate()->first();
        if($draftId===null){
            if($reservation)throw new \DomainException('PLOT_VOUCHER_NUMBER_RESERVED_BY_APPROVAL');
        }elseif(!$reservation || (int)$reservation->id!==$draftId
            ||$reservation->status!=='pending'){
            throw new \DomainException('PLOT_APPROVAL_DRAFT_NUMBER_MISMATCH');
        }
    }

    public function receive(int $project, int $customer, string $key, array $data, ?int $actor, ?int $approvedDraftId=null): array
    {
        FinanceConnectionGuard::assertAllowed();
        $this->date($data['date']); $total=$this->cents($data['amount']);
        if ($project<1 || $customer<1 || $total<1 || !preg_match('/^[A-Za-z0-9_-]{16,128}$/D',$key)) throw new \DomainException('V2_PLOT_RECEIPT_INPUT_INVALID');
        return DB::transaction(function() use($project,$customer,$key,$data,$actor,$total,$approvedDraftId):array {
            DB::table('projects')->where('id',$project)->lockForUpdate()->firstOrFail();
            if (!DB::table('leads')->where('id',$customer)->where('project_id',$project)->exists()) throw new \DomainException('V2_PLOT_CUSTOMER_PROJECT_MISMATCH');
            $bank=(int)$data['cash_account_id'];
            if (!$this->projectAccount($project,$bank)) throw new \DomainException('V2_PLOT_ACCOUNT_PROJECT_MISMATCH');
            $paymentType=(int)($data['payment_type']??1);
            $passedMethod=(string)($data['passed_source_method']??'');
            $passedId=(int)($data['passed_source_id']??0);
            if(!in_array($paymentType,[1,2,3],true)
                || ($paymentType===1 && ($passedMethod!=='' || $passedId>0))
                || ($paymentType!==1 && (
                    $passedMethod!==($paymentType===2?'online':'cheque') || $passedId<1))){
                throw new \DomainException('PPR_TYPED_BANK_SOURCE_REQUIRED');
            }
            if($paymentType!==1){
                // The debit leg must be a project-owned bank asset, not a
                // customer/receivable mapping copied from the management source.
                if(!DB::table('project_bank_accounts')
                    ->where('project_id',$project)->where('account_mapping_id',$bank)
                    ->where('is_active',1)->exists()){
                    throw new \DomainException('PPR_TYPED_BANK_DEBIT_ACCOUNT_REQUIRED');
                }
                // Approved draft already reserves this capacity. The writer
                // atomically promotes it after posting rather than double-reserving.
                if($approvedDraftId===null){
                    app(VerifiedBankInstrument::class)->inspectLocked(
                        $project,'PPR',$passedMethod,$passedId,$this->money($total));
                }
            }
            if (count($data['allocations'])<1) throw new \DomainException('V2_PLOT_RECEIPT_REQUIRES_ALLOCATION');
            $allocations=[]; $sum=0; $seen=[]; $seenPlots=[]; $lines=[['account_id'=>$bank,'debit'=>$this->money($total),'credit'=>'0.00','description'=>$data['narration']]];
            foreach ($data['allocations'] as $allocation) {
                $bookingId=(int)$allocation['booking_id']; $plotId=(int)$allocation['plot_id']; $amount=$this->cents($allocation['amount']);
                $keyPair=$bookingId.':'.$plotId;
                if ($amount<1 || isset($seen[$keyPair]) || isset($seenPlots[$plotId])) throw new \DomainException('V2_PLOT_ALLOCATION_INVALID');
                $booking=DB::table('bookings')->where('id',$bookingId)->where('project_id',$project)->where('customer_id',$customer)
                    ->where('plot_id',$plotId)->where('status','active')->where('cancel_status','0')->lockForUpdate()->first();
                if (!$booking || !DB::table('plots')->where('id',$plotId)->where('project_id',$project)->exists()) throw new \DomainException('V2_PLOT_BOOKING_PROJECT_MISMATCH');
                $account=(int)($allocation['receivable_account_id']??0);
                if (!$this->projectAccount($project,$account) || !DB::table('project_head_subheads')->where('id',$account)->where('project_id',$project)->where('customer_id',$customer)->where('plot_id',$plotId)->exists()) throw new \DomainException('V2_PLOT_RECEIVABLE_ACCOUNT_MISMATCH');
                if ($account === $bank) throw new \DomainException('V2_PLOT_RECEIPT_ACCOUNT_SIDES_INVALID');
                $sum+=$amount; $seen[$keyPair]=true; $seenPlots[$plotId]=true;
                $allocations[]=['booking_id'=>$bookingId,'plot_id'=>$plotId,'amount'=>$this->money($amount)];
                $lines[]=['account_id'=>$account,'debit'=>'0.00','credit'=>$this->money($amount),'description'=>$data['narration']];
            }
            if ($sum!==$total) throw new \DomainException('V2_PLOT_ALLOCATION_TOTAL_MISMATCH');
            $fingerprint=[$project,$customer,$data['date'],$this->money($total),
                $data['narration'],$bank,$allocations,$lines];
            if($paymentType!==1)$fingerprint[]=[
                'payment_type'=>$paymentType,'method'=>$passedMethod,'source_id'=>$passedId];
            $hash=hash('sha256',json_encode($fingerprint,JSON_THROW_ON_ERROR));
            $replay=$this->existingReplay($project,$key,'plot_receipt',$hash); if($replay) return $replay;
            $number=trim($data['receipt_number']);
            if(!preg_match('/^PPR-([1-9][0-9]*)$/D',$number,$match)) {
                throw new \DomainException('PLOT_VOUCHER_NUMBER_INVALID');
            }
            $voucherNumber=(int)$match[1];
            if($approvedDraftId===null) {
                $allocated=app(ProjectVoucherAllocator::class)->next($project,'PPR');
                if($allocated!==$voucherNumber)throw new \DomainException('PLOT_VOUCHER_NUMBER_CHANGED_REFRESH_FORM');
            }
            $this->assertApprovalReservation($project,'PPR',$number,$approvedDraftId);
            if(DB::table('finance_plot_receipts')->where('project_id',$project)->where('receipt_number',$number)->exists()) throw new \DomainException('V2_PLOT_RECEIPT_NUMBER_EXISTS');
            $source=DB::table('finance_plot_receipts')->insertGetId(['project_id'=>$project,'customer_id'=>$customer,'receipt_number'=>$number,
                'receipt_date'=>$data['date'],'amount'=>$this->money($total),'narration'=>$data['narration'],'created_by'=>$actor,'created_at'=>now(),'updated_at'=>now()]);
            foreach($allocations as $allocation) DB::table('finance_plot_receipt_allocations')->insert($allocation+['receipt_id'=>$source,'created_at'=>now(),'updated_at'=>now()]);
            $batch=$this->post($project,$key,'PR','plot_receipt',$data['date'],$lines,$hash,$actor,$voucherNumber);
            DB::table('finance_plot_receipt_postings')->insert(['posting_batch_id'=>$batch,'source_id'=>$source,'source_kind'=>'plot_receipt']);
            // The same posting transaction must persist authentic general ledger
            // debit/credit lines; existing typed rows remain linked evidence.
            if (Schema::hasTable('ledger_source_attributions')
                && Schema::hasTable('ledgers')) {
                app(TypedLedgerBackfill::class)->inspect($project,(int)$batch,true);
            } elseif (!app()->environment('testing')) {
                throw new \DomainException('GENERAL_LEDGER_SCHEMA_REQUIRED');
            }
            PostedVoucherLedgerGate::assertLinked($project, (int) $batch);
            if($paymentType!==1){
                app(TypedPlotReceiptBankCustody::class)->claimPosted(
                    $project,(int)$source,$passedMethod,$passedId,
                    $this->money($total),(int)$actor,$approvedDraftId);
            }
            return ['source_id'=>$source,'posting_batch_id'=>$batch,'replayed'=>false];
        },3);
    }

    public function purchase(int $project, string $key, array $data, ?int $actor, ?int $approvedDraftId=null): array
    {
        FinanceConnectionGuard::assertAllowed(); $this->date($data['date']);
        if($project<1 || !preg_match('/^[A-Za-z0-9_-]{16,128}$/D',$key) || count($data['items'])<1) throw new \DomainException('V2_PLOT_PURCHASE_INPUT_INVALID');
        return DB::transaction(function()use($project,$key,$data,$actor,$approvedDraftId):array {
            DB::table('projects')->where('id',$project)->lockForUpdate()->firstOrFail();
            $seller=(int)$data['seller_account_id'];
            if(!$this->projectAccount($project,$seller)) throw new \DomainException('V2_PLOT_ACCOUNT_PROJECT_MISMATCH');
            $items=[];$lines=[];$total=0;$codes=[];
            foreach($data['items'] as $item){
                $code=trim($item['plot_code']);$area4=$this->cents($item['area'],4);$rate=$this->cents($item['unit_rate']);$account=(int)$item['inventory_account_id'];
                if($code===''||$area4<1||$rate<1||isset($codes[strtolower($code)])||!$this->projectAccount($project,$account)) throw new \DomainException('V2_PLOT_PURCHASE_ITEM_INVALID');
                if ($area4 > intdiv(PHP_INT_MAX, $rate)) throw new \DomainException('V2_PLOT_PURCHASE_AMOUNT_OVERFLOW');
                $cents=(int)round(($area4*$rate)/10000,0,PHP_ROUND_HALF_UP); if($cents<1) throw new \DomainException('V2_PLOT_PURCHASE_ITEM_INVALID');
                if ($total > PHP_INT_MAX - $cents) throw new \DomainException('V2_PLOT_PURCHASE_AMOUNT_OVERFLOW');
                $codes[strtolower($code)]=true;$total+=$cents;$amount=$this->money($cents);
                $items[]=['plot_code'=>$code,'description'=>trim($item['description']),'area'=>number_format($area4/10000,4,'.',''),'unit'=>$item['unit'],'unit_rate'=>$this->money($rate),'amount'=>$amount,'inventory_account_id'=>$account,
                    'type'=>(int)$item['type'],'road_id'=>(int)$item['road_id'],'facing_id'=>(int)$item['facing_id'],'is_corner'=>(int)($item['is_corner']??0),
];
                $lines[]=['account_id'=>$account,'debit'=>$amount,'credit'=>'0.00','description'=>'Plot purchase '.$code.' · '.$item['description']];
            }
            $lines[]=['account_id'=>$seller,'debit'=>'0.00','credit'=>$this->money($total),'description'=>$data['narration']];
            if($total<1) throw new \DomainException('V2_PLOT_PURCHASE_TOTAL_INVALID');
            $hash=hash('sha256',json_encode([$project,$data['date'],$data['purchase_number'],$this->money($total),$seller,$data['narration'],$items,$lines],JSON_THROW_ON_ERROR));
            $replay=$this->existingReplay($project,$key,'plot_purchase',$hash);if($replay)return $replay;
            $number=trim($data['purchase_number']);
            if(!preg_match('/^PP-([1-9][0-9]*)$/D',$number,$match)) {
                throw new \DomainException('PLOT_VOUCHER_NUMBER_INVALID');
            }
            $voucherNumber=(int)$match[1];
            if($approvedDraftId===null) {
                $allocated=app(ProjectVoucherAllocator::class)->next($project,'PP');
                if($allocated!==$voucherNumber)throw new \DomainException('PLOT_VOUCHER_NUMBER_CHANGED_REFRESH_FORM');
            }
            $this->assertApprovalReservation($project,'PP',$number,$approvedDraftId);
            if(DB::table('finance_plot_purchases')->where('project_id',$project)->where('purchase_number',$number)->exists())throw new \DomainException('V2_PLOT_PURCHASE_NUMBER_EXISTS');
            $source=DB::table('finance_plot_purchases')->insertGetId(['project_id'=>$project,'seller_account_id'=>$seller,'purchase_number'=>$number,'purchase_date'=>$data['date'],
                'amount'=>$this->money($total),'narration'=>$data['narration'],'created_by'=>$actor,'created_at'=>now(),'updated_at'=>now()]);
            foreach($items as $item) {
                $plotId = $this->createAvailablePlot($project, $item, $actor);
                $stored = collect($item)->only(['plot_code','description','area','unit','unit_rate','amount','inventory_account_id'])->all();
                DB::table('finance_plot_purchase_items')->insert($stored+['purchase_id'=>$source,'legacy_plot_id'=>$plotId,'created_at'=>now(),'updated_at'=>now()]);
            }
            $batch=$this->post($project,$key,'PP','plot_purchase',$data['date'],$lines,$hash,$actor,$voucherNumber);
            DB::table('finance_plot_purchase_postings')->insert(['posting_batch_id'=>$batch,'source_id'=>$source,'source_kind'=>'plot_purchase']);
            // The same posting transaction must persist authentic general ledger
            // debit/credit lines; existing typed rows remain linked evidence.
            if (Schema::hasTable('ledger_source_attributions')
                && Schema::hasTable('ledgers')) {
                app(TypedLedgerBackfill::class)->inspect($project,(int)$batch,true);
            } elseif (!app()->environment('testing')) {
                throw new \DomainException('GENERAL_LEDGER_SCHEMA_REQUIRED');
            }
            PostedVoucherLedgerGate::assertLinked($project, (int) $batch);
            return ['source_id'=>$source,'posting_batch_id'=>$batch,'replayed'=>false];
        },3);
    }

    public function receipt(int $project,int $id): array
    {
        FinanceConnectionGuard::assertAllowed();
        $source=DB::table('finance_plot_receipts')->where('project_id',$project)->where('id',$id)
            ->when(Schema::hasColumn('finance_plot_receipts','is_active'),fn($q)=>$q->where('is_active',1))
            ->when(Schema::hasColumn('finance_plot_receipts','deleted_at'),fn($q)=>$q->whereNull('deleted_at'))->first();
        if(!$source) throw new \DomainException('V2_PLOT_RECEIPT_NOT_FOUND');
        $allocations=DB::table('finance_plot_receipt_allocations as a')->join('plots as p','p.id','=','a.plot_id')
            ->join('bookings as b','b.id','=','a.booking_id')->join('leads as customer','customer.id','=','b.customer_id')
            ->where('a.receipt_id',$id)->where('p.project_id',$project)->where('b.project_id',$project)
            ->where('b.customer_id',(int) $source->customer_id)->where('customer.project_id',$project)
            ->whereColumn('b.plot_id','a.plot_id')
            ->when(Schema::hasColumn('finance_plot_receipt_allocations','is_active'),fn($q)=>$q->where('a.is_active',1))
            ->orderBy('a.id')
            ->get(['a.plot_id','p.name as plot_name','a.booking_id','a.amount','customer.first_name','customer.last_name','customer.business']);
        return ['receipt'=>$source,'allocations'=>$allocations];
    }

    public function purchaseDetails(int $project,int $id): array
    {
        FinanceConnectionGuard::assertAllowed();
        $source=DB::table('finance_plot_purchases')->where('project_id',$project)->where('id',$id)
            ->when(Schema::hasColumn('finance_plot_purchases','is_active'),fn($q)=>$q->where('is_active',1))
            ->when(Schema::hasColumn('finance_plot_purchases','deleted_at'),fn($q)=>$q->whereNull('deleted_at'))->first();
        if(!$source) throw new \DomainException('V2_PLOT_PURCHASE_NOT_FOUND');
        return ['purchase'=>$source,'items'=>DB::table('finance_plot_purchase_items')->where('purchase_id',$id)
            ->when(Schema::hasColumn('finance_plot_purchase_items','is_active'),fn($q)=>$q->where('is_active',1))
            ->orderBy('id')->get()];
    }
}
