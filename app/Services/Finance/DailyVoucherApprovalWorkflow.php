<?php

namespace App\Services\Finance;

use App\Models\DailyVoucherApproval;
use App\Models\DailyVoucherApprovalPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DailyVoucherApprovalWorkflow
{
    public function policy(int $projectId, string $type): ?DailyVoucherApprovalPolicy
    {
        if (!Schema::hasTable('daily_voucher_approval_policies')) return null;
        return DailyVoucherApprovalPolicy::query()->where('project_id', $projectId)
            ->where('voucher_type', $type)->where('approval_required', true)->first();
    }

    public function submitIfRequired(int $projectId, string $type, string $sourceTable, int $sourceId, int $userId): bool
    {
        if (!Schema::hasTable('daily_voucher_approval_policies')) {
            return false;
        }

        $policy = DailyVoucherApprovalPolicy::query()
            ->where('project_id', $projectId)->where('voucher_type', $type)
            ->where('approval_required', true)->first();
        if (!$policy) {
            return false;
        }
        $configuredApprover = $policy->approver_user_id
            ? \App\Models\User::query()->find($policy->approver_user_id) : null;
        if (!VoucherApproverAccess::eligibleForAssignment($configuredApprover, $projectId)) {
            throw new \DomainException('DAILY_VOUCHER_APPROVER_NOT_CONFIGURED_OR_SELF_APPROVAL');
        }
        if ((int) $policy->approver_user_id === $userId
            && !VoucherApproverAccess::isSuperAdmin(\App\Models\User::find($userId))) {
            throw new \DomainException('DAILY_VOUCHER_APPROVER_NOT_CONFIGURED_OR_SELF_APPROVAL');
        }

        $approval = DailyVoucherApproval::query()->create([
            'project_id' => $projectId, 'voucher_type' => $type,
            'source_table' => $sourceTable, 'source_id' => $sourceId,
            'status' => 'pending', 'assigned_approver_id' => $policy->approver_user_id,
            'submitted_by' => $userId,
        ]);
        DB::table('daily_voucher_approval_events')->insert([
            'approval_id' => $approval->id, 'event' => 'submitted',
            'actor_id' => $userId, 'created_at' => now(),
        ]);
        if (Schema::hasTable('notifications')) {
            DB::table('notifications')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'App\\Notifications\\DailyVoucherApprovalRequested',
                'notifiable_type' => \App\Models\User::class,
                'notifiable_id' => $policy->approver_user_id,
                'data' => json_encode([
                    'title' => 'Voucher approval requested',
                    'message' => $type.' voucher #'.$sourceId.' is awaiting your approval.',
                    'project_id' => $projectId, 'approval_id' => $approval->id,
                    'url' => route('finance.daily-vouchers.approvals.index'),
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return true;
    }

    public function decide(DailyVoucherApproval $approval, int $actorId, string $decision, ?string $reason = null): void
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new \DomainException('DAILY_VOUCHER_APPROVAL_INVALID_DECISION');
        }
        if ($decision === 'rejected' && mb_strlen(trim((string) $reason)) < 2) {
            throw new \DomainException('DAILY_VOUCHER_REJECTION_REASON_REQUIRED');
        }

        DB::transaction(function () use ($approval, $actorId, $decision, $reason): void {
            $locked = DailyVoucherApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw new \DomainException('DAILY_VOUCHER_APPROVAL_ALREADY_PROCESSED');
            }
            $actor = \App\Models\User::query()->find($actorId);
            if (!VoucherApproverAccess::mayDecide($actor, (int) $locked->project_id,
                (int) $locked->assigned_approver_id, (int) $locked->submitted_by)) {
                throw new \DomainException('DAILY_VOUCHER_APPROVER_NOT_AUTHORIZED');
            }

            // Legacy posted cash edit: original stays posted while correction
            // is awaiting approval. Only after approval activate its matched
            // reversal and corrected leg together, in this one transaction.
            if ($decision === 'approved' && $locked->source_table === 'customer_ledger') {
                $legacyCorrection = DB::table('daily_voucher_approval_events')
                    ->where('approval_id',$locked->id)
                    ->where('event','legacy_cash_correction')
                    ->lockForUpdate()->first();
                if ($legacyCorrection) {
                    $meta = json_decode((string)$legacyCorrection->remarks,true);
                    $oldId=(int)($meta['original_source_id']??0);
                    $reverseId=(int)($meta['reversal_source_id']??0);
                    if (!$oldId || !$reverseId || $oldId===$reverseId ||
                        $reverseId===(int)$locked->source_id) {
                        throw new \DomainException('INVALID_LEGACY_CORRECTION_LINK');
                    }
                    $original=DB::table('customer_ledger')->where('id',$oldId)
                        ->where('project_id',$locked->project_id)->lockForUpdate()->first();
                    $reversal=DB::table('customer_ledger')->where('id',$reverseId)
                        ->where('project_id',$locked->project_id)->lockForUpdate()->first();
                    $corrected=DB::table('customer_ledger')->where('id',$locked->source_id)
                        ->where('project_id',$locked->project_id)->lockForUpdate()->first();
                    if (!$original || !$reversal || !$corrected ||
                        $original->transaction_type !== $locked->voucher_type ||
                        $reversal->transaction_type !== $locked->voucher_type ||
                        $corrected->transaction_type !== $locked->voucher_type ||
                        (int)$original->payment_type !== 1 || (int)$reversal->payment_type !== 1 ||
                        (int)$corrected->payment_type !== 1 ||
                        (int)$original->is_active !== 1 ||
                        (int)$reversal->is_active !== 0 ||
                        (int)$corrected->is_active !== 0 ||
                        round((float)$reversal->amount_in,2) !== round((float)$original->amount_out,2) ||
                        round((float)$reversal->amount_out,2) !== round((float)$original->amount_in,2)) {
                        throw new \DomainException('LEGACY_CASH_REVERSAL_INTEGRITY_FAILED');
                    }
                    $leg=DB::table('ledgers as ledger')
                        ->join('project_head_subheads as account','account.id','=','ledger.project_head_subheads_id')
                        ->where('ledger.customer_ledger_id',$reverseId)
                        ->where('ledger.type',$locked->voucher_type)
                        ->where('account.project_id',$locked->project_id)
                        ->lockForUpdate()->get(['ledger.id','ledger.is_active']);
                    if ($leg->count()!==1 || (int)$leg->first()->is_active!==0) {
                        throw new \DomainException('LEGACY_CASH_REVERSAL_LEG_INVALID');
                    }
                    DB::table('customer_ledger')->where('id',$reverseId)
                        ->where('project_id',$locked->project_id)->update(['is_active'=>1]);
                    DB::table('ledgers')->where('id',$leg->first()->id)->update(['is_active'=>1]);
                    DB::table('daily_voucher_approval_events')->insert([
                        'approval_id'=>$locked->id,'event'=>'legacy_cash_correction_activated',
                        'actor_id'=>$actorId,'remarks'=>'Approved original offset and corrected posting',
                        'created_at'=>now(),
                    ]);
                }
            }
            // An approved cash correction is append-only. Keep the original
            // posting active while the replacement is pending/rejected. Only
            // when the authorized approver accepts the replacement, atomically
            // deactivate the original source and its matching ledger leg.
            if ($decision === 'approved' && $locked->source_table === 'customer_ledger') {
                $revision = DB::table('daily_voucher_approval_events')
                    ->where('approval_id', $locked->id)
                    ->where('event', 'revision_from_approved')
                    ->lockForUpdate()->first();
                if ($revision) {
                    $context = json_decode((string) $revision->remarks, true);
                    $originalId = (int) ($context['old_source_id'] ?? 0);
                    $originalApprovalId = (int) ($context['old_approval_id'] ?? 0);
                    if ($originalId <= 0 || $originalApprovalId <= 0) {
                        throw new \DomainException('INVALID_APPROVED_CORRECTION_LINK');
                    }
                    $originalApproval = DailyVoucherApproval::query()
                        ->whereKey($originalApprovalId)->where('project_id', $locked->project_id)
                        ->where('source_table', 'customer_ledger')->where('source_id', $originalId)
                        ->where('voucher_type', $locked->voucher_type)->lockForUpdate()->firstOrFail();
                    if ($originalApproval->status !== 'approved') {
                        throw new \DomainException('ORIGINAL_APPROVAL_CHANGED');
                    }
                    $original = DB::table('customer_ledger')
                        ->where('id', $originalId)->where('project_id', $locked->project_id)
                        ->where('transaction_type', $locked->voucher_type)
                        ->lockForUpdate()->first();
                    if (!$original || (int) $original->payment_type !== 1 ||
                        (int) $original->is_active !== 1) {
                        throw new \DomainException('ORIGINAL_CASH_SOURCE_NOT_ACTIVE');
                    }
                    $originalLegs = DB::table('ledgers as ledger')
                        ->join('project_head_subheads as account', 'account.id', '=', 'ledger.project_head_subheads_id')
                        ->where('ledger.customer_ledger_id', $originalId)
                        ->where('ledger.type', $locked->voucher_type)
                        ->where('account.project_id', $locked->project_id)
                        ->lockForUpdate()->get(['ledger.id', 'ledger.is_active']);
                    if ($originalLegs->count() !== 1 || (int) $originalLegs->first()->is_active !== 1) {
                        throw new \DomainException('ORIGINAL_CASH_LEDGER_NOT_ACTIVE');
                    }
                    $voidReason = 'Superseded by approved voucher source #'.$locked->source_id;
                    DB::table('ledgers')->where('id', $originalLegs->first()->id)
                        ->update(['is_active'=>0, 'delete_reason'=>$voidReason, 'update_by'=>$actorId]);
                    DB::table('customer_ledger')->where('id', $originalId)
                        ->where('project_id', $locked->project_id)
                        ->update(['is_active'=>0, 'delete_reason'=>$voidReason]);
                    DB::table('daily_voucher_approval_events')->insert([
                        'approval_id'=>$originalApprovalId, 'event'=>'superseded_by_approved_correction',
                        'actor_id'=>$actorId,
                        'remarks'=>json_encode(['replacement_source_id'=>$locked->source_id,
                            'replacement_approval_id'=>$locked->id], JSON_THROW_ON_ERROR),
                        'created_at'=>now(),
                    ]);
                }
            }
            $this->setPostingState($locked, $decision === 'approved');
            $locked->forceFill([
                'status' => $decision, 'decided_by' => $actorId,
                'rejection_reason' => $decision === 'rejected' ? $reason : null,
                'decided_at' => now(),
            ])->save();
            DB::table('daily_voucher_approval_events')->insert([
                'approval_id' => $locked->id, 'event' => $decision,
                'actor_id' => $actorId, 'remarks' => $decision === 'rejected' ? $reason : null,
                'created_at' => now(),
            ]);
        });
    }

    private function setPostingState(DailyVoucherApproval $approval, bool $active): void
    {
        if ($approval->source_table === 'ledgers') {
            $ledger = DB::table('ledgers as ledger')
                ->join('project_head_subheads as account', 'account.id', '=', 'ledger.project_head_subheads_id')
                ->where('ledger.id', $approval->source_id)
                ->where('ledger.type', $approval->voucher_type)
                ->where('account.project_id', $approval->project_id)
                ->lockForUpdate()->first(['ledger.id']);
            if (!$ledger) throw new \DomainException('DAILY_VOUCHER_SOURCE_NOT_FOUND');
            DB::table('ledgers')->where('id', $ledger->id)->update(['is_active' => $active ? 1 : 0]);
            return;
        }

        if ($approval->source_table === 'customer_ledger') {
            $source = DB::table('customer_ledger')->where('id', $approval->source_id)
                ->where('project_id', $approval->project_id)->lockForUpdate()->first();
            if (!$source) {
                throw new \DomainException('DAILY_VOUCHER_SOURCE_NOT_FOUND');
            }
            $sourceUpdate = [];
            if (Schema::hasColumn('customer_ledger', 'is_active')) $sourceUpdate['is_active'] = $active ? 1 : 0;
            if (Schema::hasColumn('customer_ledger', 'is_approve')) $sourceUpdate['is_approve'] = $active ? 1 : 2;
            if ($sourceUpdate) DB::table('customer_ledger')->where('id', $source->id)->where('project_id', $approval->project_id)->update($sourceUpdate);

            DB::table('ledgers')->where('customer_ledger_id', $source->id)
                ->whereIn('project_head_subheads_id', DB::table('project_head_subheads')
                    ->select('id')->where('project_id', $approval->project_id))
                ->update(['is_active' => $active ? 1 : 0]);
            return;
        }

        if ($approval->source_table === 'journal_vouchers') {
            $voucher = DB::table('journal_vouchers')->where('id', $approval->source_id)
                ->where('project_id', $approval->project_id)->lockForUpdate()->first();
            if (!$voucher) throw new \DomainException('DAILY_VOUCHER_SOURCE_NOT_FOUND');
            DB::table('journal_vouchers')->where('id', $voucher->id)->where('project_id', $approval->project_id)
                ->update(['status' => $active ? 'approved' : 'rejected', 'updated_at' => now()]);
            if (Schema::hasColumn('ledgers', 'journal_voucher_detail_id')
                && Schema::hasColumn('journal_voucher_details', 'deleted_at')) {
                // Approve only current detail-linked JV legs. Older rejected revisions stay inactive.
                $currentIds = DB::table('journal_voucher_details')->where('journal_voucher_id', $voucher->id)
                    ->whereNull('deleted_at')->pluck('id');
                $currentLegs = DB::table('ledgers')->where('type', 'JV')->where('type_id', $voucher->id)
                    ->whereIn('journal_voucher_detail_id', $currentIds)
                    ->whereIn('project_head_subheads_id', DB::table('project_head_subheads')
                        ->select('id')->where('project_id', $approval->project_id));
                if ($currentIds->count() < 2 || (clone $currentLegs)->count() !== $currentIds->count())
                    throw new \DomainException('DAILY_VOUCHER_JV_CURRENT_POSTING_PROVENANCE_INVALID');
                $currentLegs->update(['is_active' => $active ? 1 : 0]);
            } else {
                DB::table('ledgers')->where('type', $approval->voucher_type)->where('type_id', $voucher->id)
                    ->whereIn('project_head_subheads_id', DB::table('project_head_subheads')
                        ->select('id')->where('project_id', $approval->project_id))
                    ->update(['is_active' => $active ? 1 : 0]);
            }
            return;
        }

        throw new \DomainException('DAILY_VOUCHER_SOURCE_TYPE_UNSUPPORTED');
    }
}
