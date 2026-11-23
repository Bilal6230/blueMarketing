<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audited cleared Cheque / confirmed Online custody for typed multi-plot PPR.
 * A typed PPR is a finance_plot_receipts source, NOT a customer_ledger record.
 * Both posted and pending voucher identities are stored in the existing
 * allocation table under distinct voucher_type values, never fake FK IDs.
 */
final class TypedPlotReceiptBankCustody
{
    public const POSTED='PPR_TYPED';
    public const DRAFT='PPR_TYPED_DRAFT';

    private function money(mixed $amount): string
    {
        $raw=trim((string)$amount);
        if(!preg_match('/^(0|[1-9][0-9]{0,12})(?:\.([0-9]{1,2}))?$/D',$raw,$m)){
            throw new \DomainException('Typed plot bank amount is invalid.');
        }
        return $m[1].'.'.str_pad($m[2]??'',2,'0');
    }

    private function assertTransaction(int $projectId): void
    {
        if($projectId<1 || DB::transactionLevel()<1){
            throw new \DomainException('Typed PPR bank custody requires an active project transaction.');
        }
    }

    private function allocation(int $projectId,string $type,int $voucherId): object
    {
        $entry=DB::table('finance_bank_voucher_allocations')
            ->where('project_id',$projectId)->where('voucher_type',$type)
            ->where('voucher_id',$voucherId)->lockForUpdate()->first();
        if(!$entry || DB::table('finance_bank_voucher_allocation_releases')
            ->where('allocation_id',$entry->id)->exists()){
            throw new \DomainException('Typed PPR verified allocation is missing or released.');
        }
        return $entry;
    }

    private function add(int $projectId,string $type,int $voucherId,
        string $method,int $sourceId,string $amount,int $actorId): void
    {
        if(!in_array($type,[self::POSTED,self::DRAFT],true) ||
            $voucherId<1 || $actorId<1){
            throw new \DomainException('Invalid typed PPR verified source identity.');
        }
        DB::table('finance_bank_voucher_allocations')->insert([
            'project_id'=>$projectId,'source_method'=>$method,'source_id'=>$sourceId,
            'voucher_type'=>$type,'voucher_id'=>$voucherId,'amount'=>$amount,
            'allocated_by'=>$actorId,'allocated_at'=>now(),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    private function evidence(object $entry,int $actorId,string $reason,string $action): int
    {
        if($actorId<1 || mb_strlen(trim($reason))<3 || mb_strlen($reason)>500){
            throw new \DomainException('Typed PPR release requires an actor and reason.');
        }
        return DB::table('finance_bank_voucher_allocation_releases')->insertGetId([
            'project_id'=>$entry->project_id,'allocation_id'=>$entry->id,
            'source_method'=>$entry->source_method,'source_id'=>$entry->source_id,
            'voucher_type'=>$entry->voucher_type,'voucher_id'=>$entry->voucher_id,
            'amount'=>$entry->amount,'action'=>$action,'reason'=>$reason,
            'released_by'=>$actorId,'released_at'=>now(),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function reserveDraft(int $projectId,int $draftId,string $method,int $sourceId,
        string $amount,int $actorId): void
    {
        $this->assertTransaction($projectId);
        $value=$this->money($amount);
        app(VerifiedBankInstrument::class)->inspectLocked($projectId,'PPR',
            $method,$sourceId,$value);
        $this->add($projectId,self::DRAFT,$draftId,$method,$sourceId,$value,$actorId);
    }

    public function claimPosted(int $projectId,int $receiptId,string $method,int $sourceId,
        string $amount,int $actorId,?int $draftId=null): void
    {
        $this->assertTransaction($projectId);
        $value=$this->money($amount);
        if($draftId!==null){
            $draft=DB::table('finance_plot_approval_drafts')
                ->where('project_id',$projectId)->where('id',$draftId)
                ->where('voucher_type','PPR')->where('status','pending')
                ->lockForUpdate()->first();
            if(!$draft || $draft->posted_source_id!==null){
                throw new \DomainException('Pending PPR verified draft changed before approval.');
            }
            $payload=json_decode((string)$draft->payload_json,true,512,JSON_THROW_ON_ERROR);
            if(!is_array($payload)
                || (string)($payload['passed_source_method']??'')!==$method
                || (int)($payload['passed_source_id']??0)!==$sourceId
                || $this->money($payload['amount']??'')!==$value){
                throw new \DomainException('Approved PPR source identity changed.');
            }
            $reservation=$this->allocation($projectId,self::DRAFT,$draftId);
            if($reservation->source_method!==$method
                || (int)$reservation->source_id!==$sourceId
                || $this->money($reservation->amount)!==$value){
                throw new \DomainException('PPR draft reservation changed.');
            }
            $this->evidence($reservation,$actorId,
                'Approved typed PPR draft moved to receipt #'.$receiptId,'approval_transfer');
        }
        app(VerifiedBankInstrument::class)->inspectLocked(
            $projectId,'PPR',$method,$sourceId,$value);
        $receipt=DB::table('finance_plot_receipts')
            ->where('id',$receiptId)->where('project_id',$projectId)
            ->lockForUpdate()->first();
        if(!$receipt || $this->money($receipt->amount)!==$value){
            throw new \DomainException('Posted typed PPR receipt does not match source claim.');
        }
        $this->add($projectId,self::POSTED,$receiptId,$method,$sourceId,$value,$actorId);
    }

    public function release(int $projectId,string $type,int $id,int $actorId,string $reason,
        bool $cancelPending=false): array
    {
        if(!in_array($type,[self::POSTED,self::DRAFT],true)
            || $projectId<1 || $id<1){
            throw new \DomainException('Typed PPR bank source identity invalid.');
        }
        return DB::transaction(function()use($projectId,$type,$id,$actorId,$reason,$cancelPending):array{
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->first()
                ?:throw new \DomainException('Typed PPR bank project missing.');
            $claim=$this->allocation($projectId,$type,$id);
            $table=$claim->source_method==='online'?'finance_online_intakes':
                ($claim->source_method==='cheque'?'finance_cheque_intakes':null);
            if(!$table || !DB::table($table)->where('project_id',$projectId)
                ->where('id',$claim->source_id)->lockForUpdate()->first()){
                throw new \DomainException('Verified PPR management source no longer exists.');
            }

            if($type===self::DRAFT){
                $draft=DB::table('finance_plot_approval_drafts')
                    ->where('project_id',$projectId)->where('id',$id)
                    ->where('voucher_type','PPR')->lockForUpdate()->first();
                $approval=DB::table('daily_voucher_approvals')
                    ->where('project_id',$projectId)
                    ->where('source_table','finance_plot_approval_drafts')
                    ->where('source_id',$id)->where('voucher_type','PPR')
                    ->lockForUpdate()->first();
                if(!$draft || !$approval
                    || !in_array((string)$draft->status,['pending','rejected'],true)
                    || !in_array((string)$approval->status,['pending','rejected'],true)
                    || $draft->posted_source_id!==null || $draft->posting_batch_id!==null){
                    throw new \DomainException('Typed PPR draft cannot be released after posting.');
                }
                $payload=json_decode((string)$draft->payload_json,true,512,JSON_THROW_ON_ERROR);
                if(!is_array($payload)
                    || (string)($payload['passed_source_method']??'')!==$claim->source_method
                    || (int)($payload['passed_source_id']??0)!==(int)$claim->source_id
                    || $this->money($payload['amount']??'')!==$this->money($claim->amount)){
                    throw new \DomainException('Pending PPR source or amount has changed.');
                }
                if($approval->status==='pending' && !$cancelPending){
                    throw new \DomainException('Pending PPR must be cancelled or rejected before release.');
                }
                if($approval->status==='pending'){
                    DB::table('daily_voucher_approvals')->where('id',$approval->id)
                        ->where('status','pending')->update([
                            'status'=>'rejected','decided_by'=>$actorId,
                            'rejection_reason'=>'Cancelled for bank correction: '.$reason,
                            'decided_at'=>now(),'updated_at'=>now(),
                        ]);
                }
                DB::table('finance_plot_approval_drafts')
                    ->where('project_id',$projectId)->where('id',$id)
                    ->update(['status'=>'rejected','updated_at'=>now()]);
                $action='pending_cancel_release';
            }else{
                foreach(['finance_plot_receipts','finance_plot_receipt_postings',
                    'finance_posting_batches','finance_posting_lines','ledgers',
                    'project_head_subheads','finance_plot_receipt_allocations'] as $required){
                    if(!Schema::hasTable($required)){
                        throw new \DomainException('Typed PPR posting migration is missing.');
                    }
                }
                $source=DB::table('finance_plot_receipts')
                    ->where('project_id',$projectId)->where('id',$id)
                    ->lockForUpdate()->first();
                $link=DB::table('finance_plot_receipt_postings')
                    ->where('source_id',$id)->where('source_kind','plot_receipt')
                    ->lockForUpdate()->first();
                $batch=$link?DB::table('finance_posting_batches')
                    ->where('project_id',$projectId)->where('id',$link->posting_batch_id)
                    ->whereNull('reversal_of_batch_id')->lockForUpdate()->first():null;
                if(!$source || !$link || !$batch || !$batch->posted_at
                    || (Schema::hasColumn('finance_plot_receipts','is_active')
                        && (int)$source->is_active!==1)
                    || (Schema::hasColumn('finance_plot_receipts','deleted_at')
                        && $source->deleted_at!==null)
                    || DB::table('finance_posting_batches')
                        ->where('reversal_of_batch_id',$batch->id)->exists()
                    || !DB::table('finance_posting_source_claims')
                        ->where('posting_batch_id',$batch->id)
                        ->where('source_kind','plot_receipt')->exists()
                    || $this->money($source->amount)!==$this->money($claim->amount)){
                    throw new \DomainException('Typed PPR has changed or has been reversed.');
                }
                $lines=DB::table('finance_posting_lines')
                    ->where('posting_batch_id',$batch->id)->where('is_active',1)
                    ->lockForUpdate()->get();
                $debit=$credit=0;
                if($lines->count()<2)throw new \DomainException('Typed PPR has incomplete posted legs.');
                foreach($lines as $line){
                    if(!$line->legacy_ledger_id ||
                        !DB::table('project_head_subheads')->where('id',$line->project_head_subhead_id)
                            ->where('project_id',$projectId)->exists()){
                        throw new \DomainException('Typed PPR legacy posting link invalid.');
                    }
                    $ledger=DB::table('ledgers')->where('id',$line->legacy_ledger_id)
                        ->where('project_head_subheads_id',$line->project_head_subhead_id)
                        ->where('is_active',1)->lockForUpdate()->first();
                    if(!$ledger || $this->money($ledger->amount_in)!==$this->money($line->debit)
                        || $this->money($ledger->amount_out)!==$this->money($line->credit)){
                        throw new \DomainException('Typed PPR legacy leg amount mismatch.');
                    }
                    $debit+=(int)round((float)$line->debit*100);
                    $credit+=(int)round((float)$line->credit*100);
                }
                if($debit!==$credit
                    || $debit!==(int)round((float)$claim->amount*100)){
                    throw new \DomainException('Typed PPR ledger no longer balances with source.');
                }
                $receivableTotal=DB::table('finance_plot_receipt_allocations')
                    ->where('receipt_id',$id)
                    ->when(Schema::hasColumn('finance_plot_receipt_allocations','is_active'),
                        fn($q)=>$q->where('is_active',1))->sum('amount');
                if($this->money($receivableTotal)!==$this->money($claim->amount)){
                    throw new \DomainException('Typed PPR plot allocations no longer match bank source.');
                }
                $extraLegs=DB::table('ledgers')
                    ->where('type','PPR')->where('type_id',$id)->count();
                if($extraLegs!==$lines->count()){
                    throw new \DomainException('Typed PPR has unrelated or archived extra general-ledger legs.');
                }
                if(Schema::hasColumn('finance_plot_receipts','is_active')){
                    $update=['is_active'=>0,'updated_at'=>now()];
                    if(Schema::hasColumn('finance_plot_receipts','deleted_at'))$update['deleted_at']=now();
                    DB::table('finance_plot_receipts')->where('id',$id)
                        ->where('project_id',$projectId)->update($update);
                }else{
                    throw new \DomainException('Typed PPR lifecycle migration missing.');
                }
                DB::table('finance_posting_lines')->where('posting_batch_id',$batch->id)
                    ->where('is_active',1)->update(['is_active'=>0,'updated_at'=>now()]);
                DB::table('ledgers')->whereIn('id',$lines->pluck('legacy_ledger_id')->all())
                    ->update(['is_active'=>0,'updated_at'=>now()]);
                if(Schema::hasColumn('finance_plot_receipt_allocations','is_active')){
                    DB::table('finance_plot_receipt_allocations')->where('receipt_id',$id)
                        ->update(['is_active'=>0,'updated_at'=>now()]);
                }
                $action='posted_void_release';
            }
            $releaseId=$this->evidence($claim,$actorId,$reason,$action);
            $receiptNumber=$type===self::DRAFT
                ?(string)$draft->source_number:(string)$source->receipt_number;
            return ['voucher_number'=>$receiptNumber,'action'=>$action,
                'release_id'=>$releaseId,'amount'=>(string)$claim->amount,
                'method'=>(string)$claim->source_method,
                'source_id'=>(int)$claim->source_id];
        },3);
    }
}
