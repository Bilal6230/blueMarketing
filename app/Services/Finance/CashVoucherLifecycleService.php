<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Immutable correction of an original typed customer-ledger cash source.
 * The source identity is customer_ledger.id, never its display type_id.
 */
final class CashVoucherLifecycleService
{
    public function reverse(
        int $projectId,
        int $sourceId,
        string $requestKey,
        string $effectiveDate,
        string $reason,
        ?int $actorId = null
    ): int {
        FinanceConnectionGuard::assertAllowed();
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

                ->whereIn('voucher.transaction_type', ['CR', 'CP', 'PPR', 'EC', 'ECP'])
                ->whereColumn('batch.voucher_series', 'voucher.transaction_type')
                ->lockForUpdate()
                ->first(['batch.id as posting_batch_id', 'voucher.payment_type', 'voucher.is_active']);
            if (!$source) {
                throw new \DomainException('V2_CASH_REVERSE_POSTED_SOURCE_NOT_FOUND');
            }
            if (in_array((int) ($source->payment_type ?? 1), [2, 3], true)) {
                throw new \DomainException('V2_CASH_REVERSE_BANK_CUSTODY_REQUIRED');
            }
            app(VoucherApprovalMutationGuard::class)
                ->assertNoPendingCorrection($projectId, $sourceId);
            $alreadyReversed = DB::table('finance_posting_batches')
                ->where('project_id', $projectId)
                ->where('reversal_of_batch_id', (int) $source->posting_batch_id)
                ->exists();
            if ((int) $source->is_active !== 1 && !$alreadyReversed) {
                throw new \DomainException('V2_CASH_REVERSE_INACTIVE_SOURCE');
            }

            $reversalId = (new PostingGateway())->reversePostedBatch(
                $projectId, (int) $source->posting_batch_id,
                $requestKey, $effectiveDate, $reason, $actorId
            );
            // Keep source history for printing/audit; inactive source amounts
            // must not remain in normal customer subledger reports.
            if ((int) $source->is_active === 1) {
                DB::table('customer_ledger')->where('id', $sourceId)
                    ->where('project_id', $projectId)->update([
                        'is_active' => 0, 'updated_at' => now(),
                    ]);
            }
            return $reversalId;
        }, 3);
    }
}
