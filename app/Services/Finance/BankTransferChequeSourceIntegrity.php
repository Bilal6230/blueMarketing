<?php

namespace App\Services\Finance;

/**
 * Fail-closed cheque source eligibility for V2 cash/bank withdrawals.
 * The bank management intake itself is not an accounting posting.
 */
final class BankTransferChequeSourceIntegrity
{
    public static function assert(object $source, bool $hasLedger, bool $hasAllocation): void
    {
        if ((string) ($source->transaction_type ?? '') !== 'CP'
            || (int) ($source->payment_type ?? 0) !== 3
            || (int) ($source->is_active ?? 0) !== 1
            || (int) ($source->passing_status ?? -1) !== 1
            || $hasLedger || $hasAllocation) {
            throw new \DomainException('V2_BANK_TRANSFER_CHEQUE_ALREADY_POSTED_OR_INELIGIBLE');
        }
        $incoming = self::cents((string) ($source->amount_in ?? '0'));
        $outgoing = self::cents((string) ($source->amount_out ?? '0'));
        if ($incoming !== 0 || $outgoing <= 0) {
            throw new \DomainException('V2_BANK_TRANSFER_CHEQUE_DIRECTION_INVALID');
        }
    }

    private static function cents(string $amount): int
    {
        if (!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D', $amount, $matches)) {
            throw new \DomainException('V2_BANK_TRANSFER_CHEQUE_AMOUNT_INVALID');
        }
        return (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '', 2, '0');
    }
}
