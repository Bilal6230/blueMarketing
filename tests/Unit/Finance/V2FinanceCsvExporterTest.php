<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\V2FinanceCsvExporter;
use PHPUnit\Framework\TestCase;

/** Deferred end-stage unit tests; not executed during feature implementation. */
final class V2FinanceCsvExporterTest extends TestCase
{
    public function test_trial_balance_csv_contains_exact_account_totals(): void
    {
        $report = [
            'scope' => 'typed_v2_postings_only',
            'project_id' => 1, 'from' => '2026-10-30', 'through' => '2026-10-31',
            'accounts' => [[
                'account_id' => 101, 'opening_balance' => '125.50',
                'period_debit' => '0.00', 'period_credit' => '125.50',
                'closing_balance' => '0.00',
            ]],
        ];
        $csv = (new V2FinanceCsvExporter())->trialBalance($report);
        $this->assertStringContainsString('account_id,opening_balance,period_debit', $csv);
        $this->assertStringContainsString('101,125.50,0.00,125.50,0.00', $csv);
    }

    public function test_statement_csv_quotes_unexpected_formula_like_source_text(): void
    {
        $report = [
            'scope' => 'typed_v2_postings_only',
            'project_id' => 1, 'account_id' => 101,
            'lines' => [[
                'effective_date' => '2026-10-31', 'posting_batch_id' => 2,
                'posting_line_id' => 3, 'source_kind' => '=1+1',
                'source_id' => 51, 'is_reversal' => false,
                'debit' => '0.00', 'credit' => '125.50',
                'running_balance' => '0.00',
            ]],
        ];
        $csv = (new V2FinanceCsvExporter())->accountStatement($report);
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringNotContainsString(',=1+1,', $csv);
    }

    public function test_rejects_unknown_source_scope(): void
    {
        $this->expectException(\DomainException::class);
        (new V2FinanceCsvExporter())->trialBalance([
            'scope' => 'unverified_legacy_ledgers', 'accounts' => [],
        ]);
    }
}
