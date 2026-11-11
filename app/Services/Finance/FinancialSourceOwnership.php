<?php

namespace App\Services\Finance;

use App\Models\CustomerLedger;
use App\Models\ProjectHeadSubhead;
use Illuminate\Support\Facades\DB;

/**
 * New linked finance postings must resolve to one same-project typed source.
 * Unlinked legacy families are inventoried by the read-only audit; no old
 * records are rewritten or assigned a speculative source.
 */
final class FinancialSourceOwnership
{
    public const FAMILIES = ['CR', 'CP', 'CCR', 'CDR', 'PPR', 'EC', 'ECP'];

    public function assertLinked(array $leg): void
    {
        $type = (string) ($leg['type'] ?? '');
        if (!in_array($type, self::FAMILIES, true)) {
            return;
        }
        $sourceId = (int) ($leg['customer_ledger_id'] ?? 0);
        $accountId = (int) ($leg['project_head_subheads_id'] ?? 0);
        if ($sourceId <= 0 || $accountId <= 0) {
            throw new \DomainException('FINANCE_SOURCE_ACCOUNT_REQUIRED');
        }
        $source = CustomerLedger::query()->whereKey($sourceId)->first();
        $account = ProjectHeadSubhead::query()->whereKey($accountId)->first();
        // CP clearance deliberately points to its originating CR/PPR
        // receipt. Preserve this established cross-series relationship only
        // for a canonical, source-ID-bound clearance reference.
        $isReceiptClearance = $type === 'CP' && $source &&
            in_array((string) $source->transaction_type, ['CR', 'PPR'], true) &&
            (string) ($leg['reference'] ?? '') === 'CASH_OUT_CLEARANCE#' . $sourceId;
        // Approval-controlled revisions create an INACTIVE replacement source
        // and matching INACTIVE ledger. Never require the source to be posted
        // before its approver activates both records. Active legs still require
        // an active source; a mismatched source/leg state is always rejected.
        $sourceAndLegMatch = $source &&
            ((int) $source->is_active === 1 ||
             ((int) $source->is_active === 0 && (int) ($leg['is_active'] ?? 1) === 0));
        if (!$source || !$account ||
            (!$isReceiptClearance && (string) $source->transaction_type !== $type) ||
            (int) $source->project_id !== (int) $account->project_id ||
            !$sourceAndLegMatch) {
            throw new \DomainException('FINANCE_SOURCE_OWNER_MISMATCH');
        }
        if (in_array($type, ['CCR','CDR'], true) &&
            ((int)($leg['type_id'] ?? 0) !== (int)$source->type_id ||
             (int)($leg['voucher_number'] ?? 0) !== (int)$source->type_id ||
             (int)$source->payment_type !== 1)) {
            throw new \DomainException('CASH_ONLY_SOURCE_SERIES_MISMATCH');
        }
        if (in_array($type, ['EC', 'ECP'], true) &&
            ((int) ($leg['type_id'] ?? 0) !== (int) $source->type_id ||
             (int) ($leg['voucher_number'] ?? 0) !== (int) $source->type_id ||
             (string) ($leg['reference'] ?? '') !== (string) $source->reference)) {
            throw new \DomainException('FINANCE_SOURCE_SERIES_MISMATCH');
        }
    }

    public function assertUnique(int $projectId, string $type, int $number, string $reference): void
    {
        if (!in_array($type, self::FAMILIES, true) || $projectId <= 0 ||
            $number <= 0 || trim($reference) === '') {
            throw new \DomainException('FINANCE_SOURCE_IDENTITY_INVALID');
        }
        if (CustomerLedger::query()->where('project_id', $projectId)
            ->where('transaction_type', $type)
            ->where(function ($q) use ($number, $reference) {
                $q->where('type_id', $number)->orWhere('reference', $reference);
            })->lockForUpdate()->exists()) {
            throw new \DomainException('FINANCE_SOURCE_DUPLICATE');
        }
    }
}
