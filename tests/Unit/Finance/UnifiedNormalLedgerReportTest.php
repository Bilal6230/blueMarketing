<?php

namespace Tests\Unit\Finance;

use App\Services\Finance\UnifiedNormalLedgerReport;
use Tests\TestCase;

final class UnifiedNormalLedgerReportTest extends TestCase
{
    public function test_mixed_legacy_and_typed_rows_group_by_exact_account_without_float_rounding(): void
    {
        $rows = [
            [
                'project_head_subheads_id' => 10, 'head_accounting_id' => 2, 'acct_type' => 1,
                'project_name' => 'Test Project', 'head_account_name' => 'Cash',
                'subhead_account_name' => 'Account', 'amount_in' => '100.10', 'amount_out' => '0.00',
            ],
            [
                'project_head_subheads_id' => 10, 'head_accounting_id' => 2, 'acct_type' => 1,
                'project_name' => 'Test Project', 'head_account_name' => 'Cash',
                'subhead_account_name' => 'Account', 'amount_in' => '0.00', 'amount_out' => '40.05',
            ],
            [
                'project_head_subheads_id' => 11, 'head_accounting_id' => 2, 'acct_type' => 1,
                'project_name' => 'Test Project', 'head_account_name' => 'Cash',
                'subhead_account_name' => 'Different Account', 'amount_in' => '5.25', 'amount_out' => '0.00',
            ],
        ];

        $service = new UnifiedNormalLedgerReport();
        $party = collect($service->totals($rows, 'project_head_subheads_id'))->keyBy('project_head_subheads_id');
        $this->assertCount(2, $party);
        $this->assertSame('100.10', $party[10]['total_amount_in']);
        $this->assertSame('40.05', $party[10]['total_amount_out']);
        $this->assertSame('60.05', $party[10]['balance_amount']);

        $head = $service->totals($rows, 'head_accounting_id');
        $this->assertCount(1, $head);
        $this->assertSame('105.35', $head[0]['total_amount_in']);
        $this->assertSame('40.05', $head[0]['total_amount_out']);
        $this->assertSame('65.30', $head[0]['balance_amount']);
    }

    public function test_unified_read_path_requires_exact_typed_identity_and_filters_legacy_linked_lines(): void
    {
        $service = file_get_contents(app_path('Services/Finance/UnifiedNormalLedgerReport.php'));
        $controller = file_get_contents(app_path('Http/Controllers/LedgerController.php'));

        $this->assertStringContainsString('FinanceWorkflowAccess::mergedWriterAllowed()', $service);
        $this->assertStringContainsString('PostingGateway())->postingLines(', $service);
        $this->assertStringContainsString("['legacy_ledger_id'] === null", $service);
        $this->assertStringContainsString("['posting_line_id']", $service);
        $this->assertStringNotContainsString("['type_id']", $service);
        $this->assertSame(2, substr_count($controller, 'return $this->mixedLedgerReport($request,'));
        $this->assertStringContainsString('return $this->mixedLedgerReport($request);', $controller);
    }

    public function test_invalid_amount_and_group_are_rejected(): void
    {
        $service = new UnifiedNormalLedgerReport();
        $this->expectException(\InvalidArgumentException::class);
        $service->totals([], 'reference');
    }
}
