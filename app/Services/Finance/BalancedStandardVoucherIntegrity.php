<?php

namespace App\Services\Finance;

use Illuminate\Support\Collection;

/**
 * Validates new two-sided standard CR/DR/CP/CCR/CDR postings without
 * rewriting historic one-sided vouchers. The oldest ledger is the
 * operator-selected party account; the second ledger is its counterpart.
 */
final class BalancedStandardVoucherIntegrity
{
    public static function assert(string $series, object $source, Collection $legs): void
    {
        if (!in_array($series, ['CR', 'DR', 'CP', 'CCR', 'CDR'], true)
            || $legs->count() !== 2) {
            throw new \DomainException('STANDARD_VOUCHER_TWO_LEGS_REQUIRED');
        }
        $ordered = $legs->sortBy('id')->values();
        $party = $ordered[0];
        $counter = $ordered[1];
        if ((int) $party->project_head_subheads_id < 1
            || (int) $counter->project_head_subheads_id < 1
            || (int) $party->project_head_subheads_id === (int) $counter->project_head_subheads_id
            || (string) $party->type !== $series
            || (string) $counter->type !== $series) {
            throw new \DomainException('STANDARD_VOUCHER_ACCOUNT_OR_TYPE_INVALID');
        }
        $amount = round(max((float) $source->amount_in, (float) $source->amount_out), 2);
        $incoming = in_array($series, ['CR', 'CCR'], true);
        if ($amount <= 0
            || round((float) $source->amount_in, 2) !== ($incoming ? $amount : 0.0)
            || round((float) $source->amount_out, 2) !== ($incoming ? 0.0 : $amount)) {
            throw new \DomainException('STANDARD_VOUCHER_SOURCE_AMOUNT_INVALID');
        }
        $partyDebit = round((float) $party->amount_in, 2);
        $partyCredit = round((float) $party->amount_out, 2);
        $counterDebit = round((float) $counter->amount_in, 2);
        $counterCredit = round((float) $counter->amount_out, 2);
        if ($partyDebit !== ($incoming ? $amount : 0.0)
            || $partyCredit !== ($incoming ? 0.0 : $amount)
            || $counterDebit !== ($incoming ? 0.0 : $amount)
            || $counterCredit !== ($incoming ? $amount : 0.0)) {
            throw new \DomainException('STANDARD_VOUCHER_UNBALANCED_POSTING');
        }
    }
}
