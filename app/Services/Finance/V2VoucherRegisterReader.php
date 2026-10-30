<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Source-verified project voucher register for isolated V2.
 * Original display numbers come from actual typed source PKs, never from
 * overloaded ledger.type_id or guessed voucher names.
 */
final class V2VoucherRegisterReader
{
    private const DISPLAY = [
        'journal' => ['journal_vouchers', 'voucher_number'],
        'commission' => ['commision_vouchers', 'voucher_number'],
        'cash' => ['customer_ledger', 'reference'],
        'booking' => ['booking_vouchers', 'voucher_number'],
        'labour' => ['labour_ledgers', 'id'],
        'stock_entry' => ['stock_entries', 'slip_no'],
        'stock_bill' => ['stock_bills', 'bill_no'],
    ];

    private function cents(mixed $v): int
    {
        $s = (string) $v;
        if (!preg_match('/^(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D', $s, $m)) {
            throw new \DomainException('V2_REGISTER_MONEY_INVALID');
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100) . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function add(int $a, int $b): int
    {
        if ($a > PHP_INT_MAX - $b) {
            throw new \DomainException('V2_REGISTER_OVERFLOW');
        }
        return $a + $b;
    }

    public function documents(int $projectId, ?string $from = null, ?string $through = null): array
    {
        $lines = (new V2PostingReportReader())->lines($projectId, $from, $through);
        $references = [];
        $batches = [];
        foreach ($lines as $line) {
            $kind = (string) $line['source_kind'];
            if (!isset(self::DISPLAY[$kind])) {
                throw new \DomainException('V2_REGISTER_UNKNOWN_TYPED_SOURCE');
            }
            $sourceId = (int) $line['source_id'];
            $references[$kind][$sourceId] = true;
            $id = (int) $line['posting_batch_id'];
            if (!isset($batches[$id])) {
                $batches[$id] = [
                    'posting_batch_id' => $id,
                    'source_kind' => $kind,
                    'source_id' => $sourceId,
                    'effective_date' => (string) $line['effective_date'],
                    'voucher_series' => (string) $line['voucher_series'],
                    'is_reversal' => (bool) $line['is_reversal'],
                    'reversal_of_batch_id' => $line['reversal_of_batch_id'],
                    'debit_cents' => 0,
                    'credit_cents' => 0,
                    'posting_line_count' => 0,
                ];
            }
            if ($batches[$id]['source_id'] !== $sourceId
                || $batches[$id]['source_kind'] !== $kind) {
                throw new \DomainException('V2_REGISTER_MIXED_BATCH_SOURCE');
            }
            $batches[$id]['debit_cents'] = $this->add(
                $batches[$id]['debit_cents'], $this->cents($line['debit'])
            );
            $batches[$id]['credit_cents'] = $this->add(
                $batches[$id]['credit_cents'], $this->cents($line['credit'])
            );
            $batches[$id]['posting_line_count']++;
        }
        $labels = [];
        foreach ($references as $kind => $ids) {
            [$table, $column] = self::DISPLAY[$kind];
            $labels[$kind] = DB::table($table)
                ->whereIn('id', array_keys($ids))->pluck($column, 'id')->all();
            if (count($labels[$kind]) !== count($ids)) {
                throw new \DomainException('V2_REGISTER_SOURCE_DISPLAY_MISSING');
            }
        }

        $results = [];
        foreach ($batches as $batch) {
            if ($batch['debit_cents'] < 1 || $batch['debit_cents'] !== $batch['credit_cents']) {
                throw new \DomainException('V2_REGISTER_BATCH_NOT_BALANCED');
            }
            $sourceId = $batch['source_id'];
            $kind = $batch['source_kind'];
            $label = $labels[$kind][$sourceId] ?? null;
            if ($kind === 'cash' && ($label === null || $label === '')) {
                // Actual customer_ledger PK, not reused type_id.
                $label = $batch['voucher_series'] . '-' . $sourceId;
            }
            $results[] = [
                'posting_batch_id' => $batch['posting_batch_id'],
                'source_kind' => $kind,
                'source_id' => $sourceId,
                'source_reference' => (string) $label,
                'voucher_series' => $batch['voucher_series'],
                'effective_date' => $batch['effective_date'],
                'is_reversal' => $batch['is_reversal'],
                'reversal_of_batch_id' => $batch['reversal_of_batch_id'],
                'line_count' => $batch['posting_line_count'],
                'total_debit' => $this->money($batch['debit_cents']),
                'total_credit' => $this->money($batch['credit_cents']),
            ];
        }
        return $results;
    }
}
