<?php

namespace App\Services\Finance;

use App\Models\DailyVoucherApprovalPolicy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Project-scoped approval gate around the existing same-ID atomic writer.
 * The payload is frozen when submitted; approval revalidates exact ledger
 * relationships and fingerprint immediately before the original-row update.
 */
final class BankLinkedCorrectionApproval
{
    private function policyType(string $method): string
    {
        return match ($method) {
            'online'=>'ONL_CORR',
            'cheque'=>'CHQ_CORR',
            default=>throw new \DomainException('Unsupported bank correction method.'),
        };
    }

    private function available(): void
    {
        if (!Schema::hasTable('finance_bank_correction_requests') ||
            !Schema::hasTable('daily_voucher_approval_policies')) {
            throw new \DomainException('Bank correction approval migration is missing.');
        }
    }

    public function submitReissue(int $projectId,string $method,int $sourceId,int $actorId,
        string $reason,array $changes,string $fingerprint): array
    {
        $this->available();
        if ($actorId<1 || mb_strlen(trim($reason))<3 || mb_strlen(trim($reason))>500
            || !preg_match('/^[a-f0-9]{64}$/D',$fingerprint)) {
            throw new \DomainException('A valid correction reason and preview are required.');
        }
        return DB::transaction(function()use($projectId,$method,$sourceId,$actorId,
            $reason,$changes,$fingerprint): array {
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->first()
                ?: throw new \DomainException('Project not found.');
            if(DB::table('finance_bank_correction_requests')->where('project_id',$projectId)
                ->where('source_method',$method)->where('source_id',$sourceId)
                ->where('status','pending')->lockForUpdate()->exists()) {
                throw new \DomainException('An earlier correction is still pending approval.');
            }
            $plan=app(BankSourceReissueCorrection::class)
                ->preview($projectId,$method,$sourceId,$changes);
            if(!hash_equals($plan['fingerprint'],$fingerprint)) {
                throw new \DomainException('The correction preview is stale. Review again.');
            }
            $policy=DailyVoucherApprovalPolicy::query()->where('project_id',$projectId)
                ->where('voucher_type',$this->policyType($method))->first();
            if(!$policy || !$policy->approval_required) {
                return ['pending'=>false,'result'=>app(BankSourceReissueCorrection::class)->confirm(
                    $projectId,$method,$sourceId,$actorId,$reason,$changes,$fingerprint)];
            }
            $approver=User::find($policy->approver_user_id);
            if(!VoucherApproverAccess::eligibleForAssignment($approver,$projectId)) {
                throw new \DomainException('Set a valid approver in Voucher Settings.');
            }
            if((int)$approver->id===$actorId
                && !VoucherApproverAccess::isSuperAdmin(User::find($actorId))) {
                throw new \DomainException('Staff cannot approve their own corrections.');
            }
            $id=DB::table('finance_bank_correction_requests')->insertGetId([
                'project_id'=>$projectId,'source_method'=>$method,'source_id'=>$sourceId,
                'submitted_by'=>$actorId,'assigned_approver_id'=>(int)$approver->id,
                'status'=>'pending','fingerprint'=>$fingerprint,
                'proposed_amount'=>$plan['after']['amount'],
                'proposed_vouchers_json'=>json_encode([
                    'mode'=>'reissue','changes'=>$changes,
                    'before'=>$plan['before'],'after'=>$plan['after'],
                    'original_status'=>$plan['status'],
                    'requires_replacement'=>$plan['requires_replacement'],
                    'linked_voucher_history_count'=>$plan['linked_voucher_history_count'],
                    'historical_released_count'=>$plan['historical_released_count'],
                    'released_vouchers'=>$plan['active_vouchers']],JSON_THROW_ON_ERROR),
                'reason'=>trim($reason),'created_at'=>now(),'updated_at'=>now(),
            ]);
            return ['pending'=>true,'request_id'=>$id,
                'assigned_approver_id'=>(int)$approver->id,
                'released_vouchers_on_approval'=>count($plan['active_vouchers'])];
        },3);
    }

    /** Old same-ID voucher adjustment removed from the public correction flow. */
    public function submit(int $projectId,string $method,int $sourceId,int $actorId,
        string $reason,mixed $amount,array $vouchers,string $fingerprint): array
    {
        throw new \DomainException('Use source correction with audited voucher reissue.');
    }

    public function decide(int $projectId, int $requestId, int $actorId,
        string $decision, ?string $decisionReason=null): array
    {
        $this->available();
        if (!in_array($decision,['approved','rejected'],true) ||
            ($decision==='rejected' && mb_strlen(trim((string)$decisionReason))<3)) {
            throw new \DomainException('A valid decision and rejection reason are required.');
        }
        return DB::transaction(function () use ($projectId,$requestId,$actorId,$decision,$decisionReason) {
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->first()
                ?: throw new \DomainException('Project not found.');
            $query=DB::table('finance_bank_correction_requests');
            $request=$query->where('id',$requestId)->where('project_id',$projectId)
                ->lockForUpdate()->first();
            if (!$request || $request->status!=='pending') {
                throw new \DomainException('Correction request is no longer pending.');
            }
            if (!VoucherApproverAccess::mayDecide(User::find($actorId),$projectId,
                (int)$request->assigned_approver_id,(int)$request->submitted_by)) {
                throw new \DomainException('Only the authorized independent approver can decide.');
            }
            $result=null;
            if ($decision==='approved') {
                $proposed=json_decode((string)$request->proposed_vouchers_json,true,512,JSON_THROW_ON_ERROR);
                if (!is_array($proposed) || ($proposed['mode']??'')!=='reissue'
                    || !is_array($proposed['changes']??null)) {
                    throw new \DomainException('Unsupported legacy correction request. Reject and resubmit using Reissue.');
                }
                $result=app(BankSourceReissueCorrection::class)->confirm($projectId,
                    (string)$request->source_method,(int)$request->source_id,$actorId,
                    (string)$request->reason,$proposed['changes'],
                    (string)$request->fingerprint,(int)$request->submitted_by);
            }
            DB::table('finance_bank_correction_requests')->where('id',$requestId)
                ->where('project_id',$projectId)->update([
                    'status'=>$decision,'decided_by'=>$actorId,
                    'decision_reason'=>$decisionReason,'decided_at'=>now(),'updated_at'=>now(),
                ]);
            return ['request_id'=>$requestId,'status'=>$decision,'result'=>$result];
        },3);
    }
}
