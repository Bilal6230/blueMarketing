<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

class FinanceVoucherUnifiedComponentsTest extends TestCase
{
    public function test_single_shared_popup_renderer_is_consumed_by_bank_sources_commission_draft_and_extra(): void
    {
        $layout=file_get_contents(resource_path('views/admin/layouts/master.blade.php'));
        $factory=file_get_contents(public_path('js/finance-voucher-components.js'));
        $bank=file_get_contents(public_path('js/finance-voucher-heading-columns.js'));
        $dataTable=file_get_contents(public_path('js/finance-voucher-datatable-columns.js'));
        $styles=file_get_contents(public_path('css/finance-voucher-components.css'));

        $this->assertStringContainsString('finance-voucher-components.js', $layout);
        $this->assertStringContainsString('finance-voucher-components.css', $layout);
        $this->assertStringContainsString('createFilter(options)', $factory);
        $this->assertStringContainsString('fvc-filter-popover', $factory);
        $this->assertStringContainsString('fvc-filter-title', $factory);
        $this->assertStringContainsString('fvc-sort-options', $factory);
        $this->assertStringContainsString('fvc-choices-list', $factory);
        $this->assertStringContainsString('fvc-date-input', $styles);
        $this->assertStringContainsString('fvc-page-btn', $styles);
        $this->assertStringContainsString("$.financeVoucherComponents.createFilter({kind:'heading'", $bank);
        $this->assertStringContainsString("$.financeVoucherComponents.createFilter({kind:'datatable'", $dataTable);
        $this->assertStringContainsString("adoptLegacyFilter('#jv-excel-pop'", $factory);
        $this->assertStringContainsString('finance-voucher-system.css', $layout);
    }

    public function test_cr_pagination_renderer_is_used_for_all_affected_registers(): void
    {
        $core=file_get_contents(public_path('js/finance-voucher-components.js'));
        $cheque=file_get_contents(resource_path('views/admin/finance/cheque/intake.blade.php'));
        $online=file_get_contents(resource_path('views/admin/finance/online/intake.blade.php'));
        $jv=file_get_contents(resource_path('views/admin/reports/vouchers/index.blade.php'));
        $cr=file_get_contents(resource_path('views/admin/finance/voucher/daily_directory.blade.php'));

        $this->assertStringContainsString('$.blueVoucherPagination.render(', $core);
        $this->assertStringContainsString('attachDataTablePager(selector)', $core);
        foreach (['#jv-directory','#journalTable','#bmDraftTable','#example1'] as $id) {
            $this->assertStringContainsString($id, $core);
        }
        $this->assertStringContainsString('$.blueVoucherPagination.render(', $cheque);
        $this->assertStringContainsString('$.blueVoucherPagination.render(', $online);
        $this->assertStringContainsString('$.blueVoucherPagination.render(', $cr);
        $this->assertStringContainsString('id="jv-directory"', $jv);
        $this->assertStringContainsString('fvc-pagination-footer', $core);
        $this->assertStringContainsString('fvc-pagination-info', $core);
    }

    public function test_shared_filter_state_preserves_business_specific_date_range_and_project_filters(): void
    {
        $cheque=file_get_contents(app_path('Http/Controllers/Finance/ChequeIntakeController.php'));
        $online=file_get_contents(app_path('Http/Controllers/Finance/OnlineIntakeController.php'));
        $cv=file_get_contents(app_path('Http/Controllers/CommissionVoucherController.php'));
        $core=file_get_contents(public_path('js/finance-voucher-components.js'));

        foreach (['column_date_from','column_date_to'] as $field) {
            $this->assertStringContainsString($field,$cheque);
            $this->assertStringContainsString($field,$online);
        }
        foreach (['fvd_date_from','fvd_date_to'] as $field) {
            $this->assertStringContainsString($field,$cv);
        }
        $this->assertStringContainsString('where(\'c.project_id\', $project)', $cheque);
        $this->assertStringContainsString('where(\'project_id\',$project)', $online);
        $this->assertStringContainsString('where(\'project_id\', $selectedProjectId)', $cv);
        $this->assertStringContainsString('DataTables still owns page state', $core);
    }
}
