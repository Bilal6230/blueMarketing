<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class FinanceLedgerReportModernUiTest extends TestCase
{
    public function test_party_head_and_account_type_reports_use_one_shared_table_component(): void
    {
        $party = file_get_contents(resource_path('views/admin/finance/reports/show_ledger_party.blade.php'));
        $head = file_get_contents(resource_path('views/admin/finance/reports/head_ledger.blade.php'));
        $shared = file_get_contents(resource_path('views/admin/finance/reports/partials/modern_ledger_table.blade.php'));
        $js = file_get_contents(public_path('js/finance-report-modern.js'));
        $css = file_get_contents(public_path('css/finance-report-modern.css'));

        $this->assertStringContainsString("modern_ledger_table", $party);
        $this->assertStringContainsString("modern_ledger_table", $head);
        $this->assertStringContainsString('finance-report-modern.js', $party);
        $this->assertStringContainsString('finance-report-modern.js', $head);
        $this->assertStringContainsString('finance-report-modern.css', $party);
        $this->assertStringContainsString('finance-report-modern.css', $head);
        $this->assertStringContainsString('financeReportTable', $shared);
        $this->assertStringContainsString('financeReportExports', $shared);
        $this->assertStringContainsString('financeReportClear', $shared);
        $this->assertStringContainsString("createFilter({kind:'datatable'", $js);
        $this->assertStringContainsString("attachDataTablePager('#financeReportTable')", $js);
        $this->assertStringContainsString("bindTableSkeleton('#financeReportTable')", $js);
        $this->assertStringContainsString("url:$root.attr('data-endpoint')", $js);
        $this->assertStringContainsString("d.account_type=$root.attr('data-account-type')", $js);
        $this->assertStringContainsString('min-height:calc(100vh - 260px)', $css);
        $this->assertStringNotContainsString("id=\"your-datatable-id\"", $party);
        $this->assertStringNotContainsString("id=\"your-datatable-id\"", $head);
    }
}
