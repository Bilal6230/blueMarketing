<?php

namespace App\Services\Finance;

use Illuminate\Support\Collection;

/**
 * Original single-booking PPR: the existing booking receivable row keeps
 * its exact ledger ID, with one separate bank/cash counter-row. Historical
 * PPRs with only the receivable leg are never rewritten.
 */
final class BalancedOriginalPprIntegrity
{
    public static function assert(object $source, Collection $legs, int $receivableLedgerId): void
    {
        if ($legs->count() !== 2 || $receivableLedgerId < 1
            || (string) ($source->transaction_type ?? '') !== 'PPR') {
            throw new \DomainException('PPR_BALANCED_LEDGER_IDENTITY_INVALID');
        }
        $receivable = $legs->first(fn ($leg) => (int) $leg->id === $receivableLedgerId);
        $opposite = $legs->first(fn ($leg) => (int) $leg->id !== $receivableLedgerId);
        $amount = round((float) ($source->amount_out ?? 0), 2);
        if (!$receivable || !$opposite || $amount <= 0
            || round((float) ($source->amount_in ?? 0), 2) !== 0.0
            || (int) $receivable->project_head_subheads_id === (int) $opposite->project_head_subheads_id
            || (string) $receivable->type !== 'PPR' || (string) $opposite->type !== 'PPR'
            || round((float) $receivable->amount_in, 2) !== $amount
            || round((float) $receivable->amount_out, 2) !== 0.0
            || round((float) $opposite->amount_in, 2) !== 0.0
            || round((float) $opposite->amount_out, 2) !== $amount) {
            throw new \DomainException('PPR_BALANCED_POSTING_MISMATCH');
        }
    }
}
