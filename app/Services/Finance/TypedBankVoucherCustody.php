<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BD/BW typed posting batches and pending approval drafts do not have
 * customer_ledger identities. The shared bank allocation table is keyed by
 * voucher_type + voucher_id, so use BD/BW + batch ID for posted transfers and
 * BD_DRAFT/BW_DRAFT + draft ID for pending transfers. Never treat a batch ID
 * as a customer_ledger ID or send a typed allocation to the legacy release.
 */
final class TypedBankVoucherCustody
{
    public static function series(string $kind): string
    {
        return match ($kind) {
            'deposit' => 'BD',
            'withdrawal' => 'BW',
            default => throw new \DomainException('Invalid typed bank voucher kind.'),
        };
    }

    private function cents(mixed $raw): int
    {
        $value=trim((string)$raw);
        if(!preg_match('/^(0|[1-9][0-9]{0,12})(?:\.([0-9]{1,2}))?$/D',$value,$m)){
            throw new \DomainException('Bank custody amount is invalid.');
        }
        return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
    }

    private function assertTransaction(int $projectId): void
    {
        if($projectId<1||DB::transactionLevel()<1){
            throw new \DomainException('Bank custody requires an active project-locked transaction.');
        }
    }

    private function allocate(int $projectId,string $type,int $voucherId,string $method,
        int $sourceId,string $amount,int $actorId): void
    {
        if(!in_array($type,['BD','BW','BD_DRAFT','BW_DRAFT'],true)
            ||$voucherId<1||$actorId<1){
            throw new \DomainException('Invalid bank custody allocation identity.');
        }
        DB::table('finance_bank_voucher_allocations')->insert([
            'project_id'=>$projectId,'source_method'=>$method,'source_id'=>$sourceId,
            'voucher_type'=>$type,'voucher_id'=>$voucherId,'amount'=>$amount,
            'allocated_by'=>$actorId,'allocated_at'=>now(),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    private function allocation(int $projectId,string $type,int $voucherId): object
    {
        $allocation=DB::table('finance_bank_voucher_allocations')
            ->where('project_id',$projectId)->where('voucher_type',$type)
            ->where('voucher_id',$voucherId)->lockForUpdate()->first();
        if(!$allocation || DB::table('finance_bank_voucher_allocation_releases')
            ->where('allocation_id',$allocation->id)->exists()){
            throw new \DomainException('Typed bank allocation is missing or already released.');
        }
        return $allocation;
    }

    private function releaseEvidence(object $allocation,int $actorId,
        string $reason,string $action): int
    {
        if($actorId<1 || mb_strlen(trim($reason))<3 || mb_strlen($reason)>500){
            throw new \DomainException('A valid actor and release reason are required.');
        }
        return DB::table('finance_bank_voucher_allocation_releases')->insertGetId([
            'project_id'=>$allocation->project_id,
            'allocation_id'=>$allocation->id,
            'source_method'=>$allocation->source_method,
            'source_id'=>$allocation->source_id,
            'voucher_type'=>$allocation->voucher_type,
            'voucher_id'=>$allocation->voucher_id,
            'amount'=>$allocation->amount,
            'action'=>$action,'reason'=>$reason,'released_by'=>$actorId,
            'released_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    /** Pending approval reserves its own unique allocation without posting. */
    public function reserveDraft(int $projectId,string $series,int $draftId,
        string $method,int $sourceId,string $amount,int $actorId): void
    {
        $this->assertTransaction($projectId);
        if(!in_array($series,['BD','BW'],true))throw new \DomainException('Invalid bank draft series.');
        app(VerifiedBankInstrument::class)
            ->inspectLocked($projectId,$series,$method,$sourceId,$amount);
        $this->allocate($projectId,$series.'_DRAFT',$draftId,$method,$sourceId,
            $amount,$actorId);
    }

    /** Direct, approval-off posting: allocate only after the balanced ledger exists. */
    public function claimPosted(int $projectId,string $series,int $batchId,
        string $method,int $sourceId,string $amount,int $actorId): void
    {
        $this->assertTransaction($projectId);
        if(!in_array($series,['BD','BW'],true))throw new \DomainException('Invalid bank series.');
        app(VerifiedBankInstrument::class)
            ->inspectLocked($projectId,$series,$method,$sourceId,$amount);
        $this->allocate($projectId,$series,$batchId,$method,$sourceId,$amount,$actorId);
    }

    /**
     * Approval transfers custody without deleting the draft claim: an audited
     * approval_transfer release frees its balance before the posted claim is
     * inserted, entirely within the caller's transaction.
     */
    public function promoteDraft(int $projectId,string $series,int $draftId,int $batchId,
        string $method,int $sourceId,string $amount,int $actorId): void
    {
        $this->assertTransaction($projectId);
        if(!in_array($series,['BD','BW'],true))throw new \DomainException('Invalid bank series.');
        $draft=DB::table('finance_bank_transfer_approval_drafts')
            ->where('project_id',$projectId)->where('id',$draftId)->where('status','pending')
            ->lockForUpdate()->first();
        if(!$draft || (string)$draft->passed_source_method!==$method
            ||(int)$draft->passed_source_id!==$sourceId
            ||$this->cents($draft->amount)!==$this->cents($amount)){
            throw new \DomainException('Verified bank draft identity changed before approval.');
        }
        $claim=$this->allocation($projectId,$series.'_DRAFT',$draftId);
        if($claim->source_method!==$method || (int)$claim->source_id!==$sourceId
            ||$this->cents($claim->amount)!==$this->cents($amount)){
            throw new \DomainException('Verified bank draft allocation changed.');
        }
        $this->releaseEvidence($claim,$actorId,'Approved typed bank voucher moved to posting batch #'.$batchId,
            'approval_transfer');
        $this->claimPosted($projectId,$series,$batchId,$method,$sourceId,$amount,$actorId);
    }

    /** One typed posting/draft is released without touching an unrelated legacy voucher. */
    public function release(int $projectId,string $type,int $voucherId,
        int $actorId,string $reason,bool $cancelPending=false): array
    {
        if(!in_array($type,['BD','BW','BD_DRAFT','BW_DRAFT'],true) ||
            $projectId<1||$voucherId<1){
            throw new \DomainException('Unknown typed bank source identity.');
        }
        return DB::transaction(function()use($projectId,$type,$voucherId,$actorId,$reason,$cancelPending):array{
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->first()
                ?:throw new \DomainException('Typed bank project is missing.');
            $allocation=$this->allocation($projectId,$type,$voucherId);
            $intakeTable=$allocation->source_method==='online'?'finance_online_intakes':
                ($allocation->source_method==='cheque'?'finance_cheque_intakes':null);
            if(!$intakeTable || !DB::table($intakeTable)->where('project_id',$projectId)
                ->where('id',$allocation->source_id)->lockForUpdate()->first()){
                throw new \DomainException('Typed bank management instrument is missing.');
            }
            $pending=str_ends_with($type,'_DRAFT');
            $series=$pending?substr($type,0,2):$type;
            if($pending){
                $draft=DB::table('finance_bank_transfer_approval_drafts')
                    ->where('project_id',$projectId)->where('id',$voucherId)
                    ->where('transfer_kind',$series==='BD'?'deposit':'withdrawal')
                    ->lockForUpdate()->first();
                if(!$draft || !in_array((string)$draft->status,['pending','rejected'],true)
                    ||(string)$draft->passed_source_method!==$allocation->source_method
                    ||(int)$draft->passed_source_id!==(int)$allocation->source_id
                    ||$this->cents($draft->amount)!==$this->cents($allocation->amount)
                    ||DB::table('finance_posting_batches')
                        ->where('project_id',$projectId)
                        ->where('request_key',$draft->request_key)->exists()){
                    throw new \DomainException('Typed bank pending claim has active or changed posting.');
                }
                $approval=DB::table('daily_voucher_approvals')
                    ->where('project_id',$projectId)
                    ->where('source_table','finance_bank_transfer_approval_drafts')
                    ->where('source_id',$voucherId)->where('voucher_type',$series)
                    ->lockForUpdate()->first();
                if(!$approval || !in_array((string)$approval->status,['pending','rejected'],true)){
                    throw new \DomainException('Typed bank approval custody mismatch.');
                }
                if($approval->status==='pending'&&!$cancelPending){
                    throw new \DomainException('Pending typed bank approval must be cancelled first.');
                }
                if($approval->status==='pending'){
                    DB::table('daily_voucher_approvals')->where('id',$approval->id)
                        ->where('status','pending')->update([
                            'status'=>'rejected','decided_by'=>$actorId,
                            'rejection_reason'=>'Cancelled due to bank source correction: '.$reason,
                            'decided_at'=>now(),'updated_at'=>now(),
                        ]);
                }
                DB::table('finance_bank_transfer_approval_drafts')
                    ->where('id',$voucherId)->where('project_id',$projectId)
                    ->update(['status'=>'rejected','updated_at'=>now()]);
                $action='pending_cancel_release';
            }else{
                foreach(['finance_posting_batches','finance_posting_lines',
                    'finance_bank_transfer_sources','ledgers','project_head_subheads'] as $required){
                    if(!Schema::hasTable($required))throw new \DomainException('Typed bank posting schema missing.');
                }
                $batch=DB::table('finance_posting_batches')
                    ->where('project_id',$projectId)->where('id',$voucherId)
                    ->where('voucher_series',$series)->lockForUpdate()->first();
                $source=DB::table('finance_bank_transfer_sources')
                    ->where('project_id',$projectId)->where('posting_batch_id',$voucherId)
                    ->where('transfer_kind',$series==='BD'?'deposit':'withdrawal')
                    ->lockForUpdate()->first();
                if(!$batch||!$batch->posted_at||$batch->reversal_of_batch_id!==null
                    ||!$source||(isset($source->is_active)&&(int)$source->is_active!==1)
                    ||(isset($source->deleted_at)&&$source->deleted_at!==null)
                    ||$source->linked_customer_ledger_id!==null
                    ||$this->cents($source->amount)!==$this->cents($allocation->amount)
                    ||DB::table('finance_posting_batches')
                        ->where('reversal_of_batch_id',$voucherId)->exists()
                    ||!DB::table('finance_posting_source_claims')
                        ->where('posting_batch_id',$voucherId)
                        ->where('source_kind','bank_transfer')->exists()){
                    throw new \DomainException('Typed bank source is not a matching active posting.');
                }
                $lines=DB::table('finance_posting_lines')
                    ->where('posting_batch_id',$voucherId)->where('is_active',1)
                    ->orderBy('id')->lockForUpdate()->get();
                $debit=$credit=0;
                if($lines->count()<2)throw new \DomainException('Typed bank has incomplete active posting.');
                foreach($lines as $line){
                    if(!$line->legacy_ledger_id)throw new \DomainException('Typed bank ledger link missing.');
                    if(!DB::table('project_head_subheads')->where('project_id',$projectId)
                        ->where('id',$line->project_head_subhead_id)->exists()){
                        throw new \DomainException('Typed bank account crossed projects.');
                    }
                    $ledger=DB::table('ledgers')->where('id',$line->legacy_ledger_id)
                        ->where('project_head_subheads_id',$line->project_head_subhead_id)
                        ->where('type',$series)->where('type_id',$voucherId)
                        ->where('is_active',1)->lockForUpdate()->first();
                    if(!$ledger || $this->cents($ledger->amount_in)!==$this->cents($line->debit)
                        ||$this->cents($ledger->amount_out)!==$this->cents($line->credit)){
                        throw new \DomainException('Typed bank ledger leg does not match source.');
                    }
                    $debit+=$this->cents($line->debit);
                    $credit+=$this->cents($line->credit);
                }
                if($debit!==$credit || $debit!==$this->cents($allocation->amount)){
                    throw new \DomainException('Typed bank posting does not balance with allocated source.');
                }
                DB::table('finance_posting_lines')->where('posting_batch_id',$voucherId)
                    ->where('is_active',1)->update(['is_active'=>0,'updated_at'=>now()]);
                DB::table('ledgers')->whereIn('id',$lines->pluck('legacy_ledger_id')->all())
                    ->update(['is_active'=>0,'updated_at'=>now()]);
                DB::table('finance_bank_transfer_sources')
                    ->where('project_id',$projectId)->where('posting_batch_id',$voucherId)
                    ->update(['is_active'=>0,'deleted_at'=>now(),'updated_at'=>now()]);
                $action='posted_void_release';
            }
            $releaseId=$this->releaseEvidence($allocation,$actorId,$reason,$action);
            return ['voucher_number'=>$type.'-'.$voucherId,
                'action'=>$action,'amount'=>(string)$allocation->amount,
                'release_id'=>$releaseId,'method'=>(string)$allocation->source_method,
                'source_id'=>(int)$allocation->source_id];
        },3);
    }
}
