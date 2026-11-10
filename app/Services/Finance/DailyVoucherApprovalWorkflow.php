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
            DB::table('ledgers')->where('type', $approval->voucher_type)->where('type_id', $voucher->id)
                ->whereIn('project_head_subheads_id', DB::table('project_head_subheads')
                    ->select('id')->where('project_id', $approval->project_id))
                ->update(['is_active' => $active ? 1 : 0]);
            return;
        }

        throw new \DomainException('DAILY_VOUCHER_SOURCE_TYPE_UNSUPPORTED');
    }
}
