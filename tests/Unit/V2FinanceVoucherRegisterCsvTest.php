<?php

namespace Tests\Unit;

use App\Services\Finance\V2FinanceCsvExporter;
use PHPUnit\Framework\TestCase;

final class V2FinanceVoucherRegisterCsvTest extends TestCase
{
    public function test_booking_stock_labour_and_voucher_rows_export_with_fixed_columns(): void
    {
        $documents = [];
        foreach (['journal', 'commission', 'cash', 'booking', 'labour', 'stock_entry', 'stock_bill'] as $i => $kind) {
            $documents[] = [
                'posting_batch_id' => $i + 1,
                'source_kind' => $kind,
                'source_id' => $i + 11,
                'source_reference' => 'REF-' . ($i + 1),
                'voucher_series' => strtoupper($kind),
                'effective_date' => '2026-10-31',
                'is_reversal' => false,
                'reversal_of_batch_id' => null,
                'line_count' => 2,
                'total_debit' => '50.00',
                'total_credit' => '50.00',
            ];
        }
        $csv = (new V2FinanceCsvExporter())->voucherRegister($documents);
        $lines = array_filter(explode("\n", trim($csv)));
        self::assertCount(8, $lines);
        self::assertStringContainsString('source_reference', $lines[0]);
        self::assertStringContainsString('stock_bill', $lines[7]);
        self::assertStringContainsString('booking', $lines[4]);
    }

    public function test_formula_injection_in_source_reference_is_escaped(): void
    {
        $csv = (new V2FinanceCsvExporter())->voucherRegister([[
            'posting_batch_id' => 1,
            'source_kind' => 'booking',
            'source_id' => 2,
            'source_reference' => '=HYPERLINK("https://invalid.example")',
            'voucher_series' => 'BR',
            'effective_date' => '2026-10-31',
            'is_reversal' => true,
            'reversal_of_batch_id' => 3,
            'line_count' => 2,
            'total_debit' => '100.00',
            'total_credit' => '100.00',
        ]]);
        self::assertStringContainsString("'=HYPERLINK(", $csv);
        self::assertStringContainsString(',1,3,2,100.00,100.00', $csv);
    }
}
