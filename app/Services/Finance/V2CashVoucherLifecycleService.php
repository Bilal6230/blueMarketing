<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Immutable correction of an original typed customer-ledger cash source.
 * The source identity is customer_ledger.id, never its display type_id.
 */
final class V2CashVoucherLifecycleService
{
    public function reverse(
        int $projectId,
        int $sourceId,
        string $requestKey,
        string $effectiveDate,
        string $reason,
        ?int $actorId = null
    ): int {
        V2IsolatedConnectionGuard::assertAllowed();
        if ($projectId < 1 || $sourceId < 1) {
            throw new \DomainException('V2_CASH_REVERSE_IDENTITY_INVALID');
        }

        return DB::transaction(function () use ($projectId, $sourceId, $requestKey, $effectiveDate, $reason, $actorId): int {
            $source = DB::table('finance_posting_cash_sources as posting')
                ->join('finance_posting_batches as batch', 'batch.id', '=', 'posting.posting_batch_id')
                ->join('finance_posting_source_claims as claim', 'claim.posting_batch_id', '=', 'batch.id')
                ->join('customer_ledger as voucher', 'voucher.id', '=', 'posting.customer_ledger_id')
                ->where('posting.customer_ledger_id', $sourceId)
                ->where('voucher.project_id', $projectId)
                ->where('batch.project_id', $projectId)
                ->where('claim.source_kind', 'cash')
                ->whereNull('batch.reversal_of_batch_id')
                ->where('voucher.is_active', 1)
                ->whereIn('voucher.transaction_type', ['CR', 'CP', 'PPR', 'EC', 'ECP'])
                ->whereColumn('batch.voucher_series', 'voucher.transaction_type')
                ->lockForUpdate()
                ->first(['batch.id as posting_batch_id', 'voucher.payment_type']);
            if (!$source) {
                throw new \DomainException('V2_CASH_REVERSE_POSTED_SOURCE_NOT_FOUND');
            }
            if (in_array((int) ($source->payment_type ?? 1), [2, 3], true)) {
                throw new \DomainException('V2_CASH_REVERSE_BANK_CUSTODY_REQUIRED');
            }
            app(VoucherApprovalMutationGuard::class)
                ->assertNoPendingCorrection($projectId, $sourceId);

            return (new V2PostingGateway())->reversePostedBatch(
                $projectId, (int) $source->posting_batch_id,
                $requestKey, $effectiveDate, $reason, $actorId
            );
        }, 3);
    }
}
