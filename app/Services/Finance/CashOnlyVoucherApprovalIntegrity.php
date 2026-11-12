<?php

namespace App\Services\Finance;

/**
 * Immutable CCR/CDR source-to-ledger checks at first approval.
 * Rejected decisions stay available even if a posting has stale amounts.
 */
final class CashOnlyVoucherApprovalIntegrity
{
    public static function assert(string $series, object $source, object $leg): void
    {
        if (!in_array($series, ['CCR', 'CDR'], true)
            || (string)($source->transaction_type ?? '') !== $series
            || (string)($leg->type ?? '') !== $series
            || (int)($source->payment_type ?? 0) !== 1
            || (int)($source->type_id ?? 0) < 1
            || (int)($leg->type_id ?? 0) !== (int)$source->type_id
            || (int)($leg->voucher_number ?? 0) !== (int)$source->type_id
            || (int)($source->is_active ?? -1) !== 0
            || (int)($leg->is_active ?? -1) !== 0) {
            throw new \DomainException('Cash-only voucher approval identity or posting state mismatch.');
        }

        $sourceIn=round((float)($source->amount_in ?? 0),2);
        $sourceOut=round((float)($source->amount_out ?? 0),2);
        $legIn=round((float)($leg->amount_in ?? 0),2);
        $legOut=round((float)($leg->amount_out ?? 0),2);

        if (($series==='CCR' && ($sourceIn<=0 || $sourceOut!=0 || $legIn!==$sourceIn || $legOut!=0))
            || ($series==='CDR' && ($sourceOut<=0 || $sourceIn!=0 || $legOut!==$sourceOut || $legIn!=0))) {
            throw new \DomainException('Cash-only voucher incoming/outgoing ledger amount mismatch.');
        }
    }
}
