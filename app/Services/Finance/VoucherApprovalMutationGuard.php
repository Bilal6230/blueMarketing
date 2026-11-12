<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Blocks legacy edits/reversals for approval-tracked sources.
 * Called INSIDE the mutation transaction after the source is locked.
 * Read-only lookup; never changes accounting rows or approval history.
 */
final class VoucherApprovalMutationGuard
{
    /**
     * Preserve the original posting while a replacement awaits an approver.
     * This also prevents a soft delete from invalidating an in-flight edit.
     */
    public function assertNoPendingCorrection(int $projectId, int $sourceId): void
    {
        if ($projectId < 1 || $sourceId < 1 ||
            !Schema::hasTable('daily_voucher_approvals') ||
            !Schema::hasTable('daily_voucher_approval_events')) return;

        $events = DB::table('daily_voucher_approval_events as event')
            ->join('daily_voucher_approvals as approval','approval.id','=','event.approval_id')
            ->where('approval.project_id',$projectId)
            ->where('approval.status','pending')
            ->whereIn('event.event',['legacy_cash_correction','revision_from_approved'])
            ->where(function ($query) use ($sourceId) {
                $query->where('event.remarks','like','%"original_source_id":'.$sourceId.',%')
                    ->orWhere('event.remarks','like','%"old_source_id":'.$sourceId.',%');
            })
            ->get(['event.remarks']);
        foreach ($events as $event) {
            $meta=json_decode((string)$event->remarks,true);
            if (is_array($meta) &&
                (int)($meta['original_source_id'] ?? $meta['old_source_id'] ?? 0)===$sourceId) {
                throw new \DomainException('This voucher has a pending correction. Decide it before editing or deactivating the original.');
            }
        }
    }

    public function assertLegacyMutationAllowed(int $projectId, string $sourceTable, int $sourceId): void
    {
        if (!in_array($sourceTable, ['journal_vouchers', 'customer_ledger'], true)) {
            throw new \InvalidArgumentException('Unsupported voucher source for lifecycle guard.');
        }
        if ($sourceTable === 'customer_ledger') {
            $this->assertNoPendingCorrection($projectId, $sourceId);
            // All bank-funded vouchers, even those posted with approval OFF,
            // must use their audited allocation-aware Release/Void lifecycle.
            if (Schema::hasTable('finance_bank_voucher_allocations') &&
                DB::table('finance_bank_voucher_allocations')
                    ->where('project_id',$projectId)
                    ->where('voucher_id',$sourceId)->exists()) {
                throw new \DomainException(
                    'Verified bank allocation blocks legacy edit/delete/reversal. Use audited Release/Void.'
                );
            }
        }

        if (!Schema::hasTable('daily_voucher_approvals')) return;
        $approval = DB::table('daily_voucher_approvals')
            ->where('project_id', $projectId)
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->first(['id', 'status']);

        if ($approval) {
            throw new \DomainException(
                'This voucher has approval status '.strtoupper((string) $approval->status).
                '. Direct edit/reversal/deletion is blocked; use the audited approval lifecycle.'
            );
        }
    }
}
