<?php

namespace App\Services\Finance;

use App\Models\ProjectHeadSubhead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BD/BW draft custody. A pending/rejected transfer creates no posting batch,
 * posting lines or ledger movement. A posting is made only on approval.
 */
final class BankTransferApprovalDraftWriter
{
    public function submit(int $projectId,int $bankId,int $cashId,string $kind,
        string $key,string $date,string $amount,string $narration,int $actorId,
        ?int $chequeSourceId=null, ?string $passedSourceMethod=null,
        ?int $passedSourceId=null): array
    {
        FinanceConnectionGuard::assertAllowed();
        if(!Schema::hasTable('finance_bank_transfer_approval_drafts')){
            throw new \DomainException('BANK_APPROVAL_DRAFT_MIGRATION_REQUIRED');
        }
        if(!in_array($kind,['deposit','withdrawal'],true)||$projectId<1||$bankId<1||$cashId<1
            ||!preg_match('/^[A-Za-z0-9_-]{16,128}$/D',$key)
            ||!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D',$amount)
            ||(float)$amount<=0||trim($narration)===''
            ||!($day=\DateTimeImmutable::createFromFormat('!Y-m-d',$date))
            ||$day->format('Y-m-d')!==$date
            ||($chequeSourceId!==null&&$kind!=='withdrawal')
            ||(($passedSourceMethod===null)!==($passedSourceId===null))
            ||($passedSourceMethod!==null &&
                (!in_array($passedSourceMethod,['online','cheque'],true)
                    ||$passedSourceId<1||$chequeSourceId!==null))){
            throw new \DomainException('BANK_APPROVAL_DRAFT_INPUT_INVALID');
        }
        $parts=explode('.',(string)$amount,2);
        $normalized=$parts[0].'.'.str_pad($parts[1]??'',2,'0');
        $fingerprint=[$projectId,$bankId,$cashId,$kind,$date,$normalized,
            trim($narration),$chequeSourceId];
        if($passedSourceMethod!==null)$fingerprint[]=['method'=>$passedSourceMethod,'source_id'=>$passedSourceId];
        $payload=hash('sha256',json_encode($fingerprint,JSON_THROW_ON_ERROR));
        return DB::transaction(function()use($projectId,$bankId,$cashId,$kind,$key,$date,
            $normalized,$narration,$actorId,$chequeSourceId,$passedSourceMethod,$passedSourceId,$payload){
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->firstOrFail();
            $existing=DB::table('finance_bank_transfer_approval_drafts')
                ->where('project_id',$projectId)->where('request_key',$key)->lockForUpdate()->first();
            if($existing){
                if(!hash_equals((string)$existing->payload_sha256,$payload)
                    ||$existing->transfer_kind!==$kind){
                    throw new \DomainException('BANK_APPROVAL_REQUEST_KEY_CONFLICT');
                }
                return ['approval_draft_id'=>(int)$existing->id,
                    'voucher_number'=>(int)$existing->voucher_number,'replayed'=>true,
                    'pending'=>$existing->status==='pending'];
            }
            $bank=DB::table('project_bank_accounts')->where('id',$bankId)
                ->where('project_id',$projectId)->where('is_active',1)->lockForUpdate()->first();
            if(!$bank || !ProjectHeadSubhead::availableForPosting()
                ->where('project_id',$projectId)->whereKey($bank->account_mapping_id)->exists()){
                throw new \DomainException('BANK_APPROVAL_BANK_ACCOUNT_INVALID');
            }
            if((int)$bank->account_mapping_id===$cashId){
                throw new \DomainException('BANK_APPROVAL_COUNTER_ACCOUNT_DUPLICATES_BANK');
            }
            if($passedSourceMethod===null){
                if(!ProjectHeadSubhead::availableForPosting()->where('project_id',$projectId)
                    ->whereKey($cashId)->where(function($q){
                        $q->whereHas('headAccounting',fn($h)=>$h->where('name','LIKE','%cash%'))
                            ->orWhereHas('subheadAccounting',fn($h)=>$h->where('name','LIKE','%cash%'));
                    })->exists()){
                    throw new \DomainException('BANK_APPROVAL_CASH_ACCOUNT_INVALID');
                }
            }else{
                if(!Schema::hasColumn('finance_bank_transfer_approval_drafts','passed_source_method')
                    ||!Schema::hasColumn('finance_bank_transfer_approval_drafts','passed_source_id')){
                    throw new \DomainException('Bank source approval identity migration is required.');
                }
                $verified=app(VerifiedBankInstrument::class)->inspectLocked(
                    $projectId,TypedBankVoucherCustody::series($kind),
                    $passedSourceMethod,$passedSourceId,$normalized);
                if((int)$verified['account_mapping_id']!==$cashId){
                    throw new \DomainException('Verified source counter-account does not match project mapping.');
                }
            }
            if($chequeSourceId!==null){
                // The verified cheque must still pass the BankTransferWriter's
                // full custody checks on approval; reserve the link among drafts.
                $cheque=DB::table('customer_ledger')->where('id',$chequeSourceId)
                    ->where('project_id',$projectId)->where('transaction_type','CP')
                    ->where('payment_type',3)->where('is_active',1)->where('passing_status',1)->first();
                if(!$cheque||DB::table('finance_bank_transfer_approval_drafts')
                    ->where('project_id',$projectId)->where('linked_customer_ledger_id',$chequeSourceId)
                    ->where('status','pending')->exists()){
                    throw new \DomainException('BANK_APPROVAL_CHEQUE_SOURCE_INVALID');
                }
            }
            $series=$kind==='deposit'?'BD':'BW';
            $legacyMax=(int)DB::table('finance_posting_batches')
                ->where('project_id',$projectId)->where('voucher_series',$series)
                ->selectRaw('MAX(COALESCE(voucher_number,id)) AS maximum')->first()?->maximum;
            $number=(new ProjectVoucherAllocator())->next($projectId,$series,$legacyMax);
            $sourceFields=$passedSourceMethod!==null?[
                'passed_source_method'=>$passedSourceMethod,
                'passed_source_id'=>$passedSourceId,
            ]:[];
            $id=DB::table('finance_bank_transfer_approval_drafts')->insertGetId([
                'project_id'=>$projectId,'transfer_kind'=>$kind,
                'bank_account_id'=>$bankId,'cash_account_id'=>$cashId,
                'linked_customer_ledger_id'=>$chequeSourceId,'effective_date'=>$date,
                'amount'=>$normalized,'narration'=>trim($narration),
                'request_key'=>$key,'payload_sha256'=>$payload,
                'voucher_number'=>$number,'status'=>'pending','created_by'=>$actorId,
                'created_at'=>now(),'updated_at'=>now(),
            ]+$sourceFields);
            if(!app(DailyVoucherApprovalWorkflow::class)->submitIfRequired(
                $projectId,$series,'finance_bank_transfer_approval_drafts',(int)$id,$actorId)){
                throw new \DomainException('BANK_APPROVAL_POLICY_CHANGED');
            }
            if($passedSourceMethod!==null){
                app(TypedBankVoucherCustody::class)->reserveDraft(
                    $projectId,$series,(int)$id,$passedSourceMethod,$passedSourceId,
                    $normalized,$actorId);
            }
            return ['approval_draft_id'=>(int)$id,'voucher_number'=>$number,
                'replayed'=>false,'pending'=>true];
        },3);
    }
}
