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

    public function submit(int $projectId, string $method, int $sourceId, int $actorId,
        string $reason, mixed $amount, array $vouchers, string $fingerprint): array
    {
        $this->available();
        if ($actorId<1 || mb_strlen(trim($reason))<3 || mb_strlen(trim($reason))>500 ||
            !preg_match('/^[a-f0-9]{64}$/D',$fingerprint)) {
            throw new \DomainException('Valid correction reason and preview are required.');
        }
        return DB::transaction(function () use ($projectId,$method,$sourceId,$actorId,$reason,$amount,$vouchers,$fingerprint) {
            DB::table('projects')->where('id',$projectId)->lockForUpdate()->first()
                ?: throw new \DomainException('Project not found.');
            $table='finance_bank_correction_requests';
            if(DB::table($table)->where('project_id',$projectId)
                ->where('source_method',$method)->where('source_id',$sourceId)
                ->where('status','pending')->lockForUpdate()->exists()) {
                throw new \DomainException('A bank correction already awaits approval for this source.');
            }
            $plan=app(BankLinkedCorrection::class)->preview($projectId,$method,$sourceId,$amount,$vouchers);
            if (!hash_equals($plan['fingerprint'],$fingerprint)) {
                throw new \DomainException('Correction changed since preview. Review it again.');
            }
            $policy=DailyVoucherApprovalPolicy::query()->where('project_id',$projectId)
                ->where('voucher_type',$this->policyType($method))->first();
            if (!$policy || !$policy->approval_required) {
                return ['pending'=>false,'result'=>app(BankLinkedCorrection::class)->confirm(
                    $projectId,$method,$sourceId,$actorId,$reason,$amount,$vouchers,$fingerprint)];
            }
            $approver=User::find($policy->approver_user_id);
            if (!VoucherApproverAccess::eligibleForAssignment($approver,$projectId)) {
                throw new \DomainException('Configure a valid project approver in Voucher Settings.');
            }
            if ((int)$approver->id===$actorId && !VoucherApproverAccess::isSuperAdmin(User::find($actorId))) {
                throw new \DomainException('Staff cannot approve their own correction.');
            }
            $id=DB::table($table)->insertGetId([
                'project_id'=>$projectId,'source_method'=>$method,'source_id'=>$sourceId,
                'submitted_by'=>$actorId,'assigned_approver_id'=>(int)$approver->id,
                'status'=>'pending','fingerprint'=>$fingerprint,
                'proposed_amount'=>$plan['amount_after'],
                'proposed_vouchers_json'=>json_encode($vouchers,JSON_THROW_ON_ERROR),
                'reason'=>trim($reason),'created_at'=>now(),'updated_at'=>now(),
            ]);
            return ['pending'=>true,'request_id'=>$id,'assigned_approver_id'=>(int)$approver->id,
                'amount_before'=>$plan['amount_before'],'amount_after'=>$plan['amount_after']];
        },3);
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
                if (!is_array($proposed)) throw new \DomainException('Invalid persisted correction proposal.');
                $result=app(BankLinkedCorrection::class)->confirm($projectId,
                    (string)$request->source_method,(int)$request->source_id,$actorId,
                    (string)$request->reason,$request->proposed_amount,$proposed,
                    (string)$request->fingerprint);
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
