<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Isolated V2 JV/CV posted-document lifecycle.
 * Original records remain immutable. A changed posted source must be handled
 * through a separately approved new document after reversing the original.
 */
final class V2VoucherLifecycleService
{
    public function reverse(
        int $projectId,
        string $kind,
        int $sourceId,
        string $requestKey,
        string $effectiveDate,
        string $reason,
        ?int $actorId = null
    ): int {
        V2IsolatedConnectionGuard::assertAllowed();
        if (!in_array($kind, ['journal', 'commission'], true)
            || $projectId < 1 || $sourceId < 1) {
            throw new \DomainException('V2_VOUCHER_LIFECYCLE_INVALID_IDENTITY');
        }

        return DB::transaction(function () use (
            $projectId, $kind, $sourceId, $requestKey,
            $effectiveDate, $reason, $actorId
        ): int {
            $sourceTable = $kind === 'journal'
                ? 'finance_posting_journal_sources'
                : 'finance_posting_commission_sources';
            $sourceColumn = $kind === 'journal'
                ? 'journal_voucher_id'
                : 'commision_voucher_id';
            $batch = DB::table($sourceTable . ' as source')
                ->join('finance_posting_batches as batch', 'batch.id', '=', 'source.posting_batch_id')
                ->join('finance_posting_source_claims as claim', 'claim.posting_batch_id', '=', 'batch.id')
                ->where('source.' . $sourceColumn, $sourceId)
                ->where('claim.source_kind', $kind)
                ->where('batch.project_id', $projectId)
                ->whereNull('batch.reversal_of_batch_id')
                ->select('batch.id')
                ->lockForUpdate()
                ->first();
            if (!$batch) {
                throw new \DomainException('V2_VOUCHER_LIFECYCLE_SOURCE_NOT_POSTED');
            }
            return (new V2PostingGateway())->reversePostedBatch(
                $projectId, (int) $batch->id, $requestKey,
                $effectiveDate, $reason, $actorId
            );
        }, 3);
    }
}
