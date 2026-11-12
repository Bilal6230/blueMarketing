<?php

namespace App\Services\Finance;

/**
 * Immutable bank/cheque identity captured when a rejected BOR/BOP is resubmitted.
 * Do not approve a pending revision if its instrument was modified meanwhile.
 */
final class BankVoucherRevisionEvidence
{
    public static function capture(object $source, string $series, object $allocation): array
    {
        self::assertSeries($series, $source);
        $amount = round((float) $allocation->amount, 2);
        if (!in_array((string)$allocation->source_method,['online','cheque'],true)
            || ((string)$allocation->source_method==='online'?2:3)!==(int)$source->payment_type) {
            throw new \DomainException('Bank revision source method mismatch.');
        }
        if ($amount <= 0) {
            throw new \DomainException('Invalid bank revision allocation.');
        }

        return [
            'bank_series' => $series,
            'bank_source_method' => (string) $allocation->source_method,
            'bank_source_id' => (int) $allocation->source_id,
            'bank_allocation_id' => (int) $allocation->id,
            'bank_allocation_amount' => number_format($amount, 2, '.', ''),
            'bank_id' => (int) $source->bank_id,
            'bank_reference' => (string) $source->t_number,
            'bank_passing_date' => substr((string) $source->passing_date, 0, 10),
            'bank_cheque_purpose' => trim((string) $source->cheque_purpose),
        ];
    }

    public static function assertUnchanged(
        array $before,
        object $source,
        object $allocation,
        string $series
    ): void {
        self::assertSeries($series, $source);

        // Older snapshots without immutable evidence cannot be used to
        // silently approve an instrument revision. Reject and resubmit.
        $expectedKeys = [
            'bank_series','bank_source_method','bank_source_id','bank_allocation_id',
            'bank_allocation_amount','bank_id','bank_reference',
            'bank_passing_date','bank_cheque_purpose',
        ];
        foreach ($expectedKeys as $key) {
            if (!array_key_exists($key, $before)) {
                throw new \DomainException('Bank revision snapshot lacks immutable instrument evidence.');
            }
        }
        $captured=self::capture($source, $series, $allocation);
        foreach ($expectedKeys as $key) {
            if ((string) $before[$key] !== (string) $captured[$key]) {
                throw new \DomainException('Bank instrument changed while revision awaited approval.');
            }
        }
    }

    private static function assertSeries(string $series, object $source): void
    {
        if (!in_array($series, ['BOR','BOP'], true)
            || (string) $source->transaction_type !== ($series === 'BOR' ? 'CR' : 'CP')
            || !in_array((int) $source->payment_type, [2,3], true)) {
            throw new \DomainException('Bank revision source series changed.');
        }
    }
}
