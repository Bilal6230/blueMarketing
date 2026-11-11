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
    public function assertLegacyMutationAllowed(int $projectId, string $sourceTable, int $sourceId): void
    {
        if (!in_array($sourceTable, ['journal_vouchers', 'customer_ledger'], true)) {
            throw new \InvalidArgumentException('Unsupported voucher source for lifecycle guard.');
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
