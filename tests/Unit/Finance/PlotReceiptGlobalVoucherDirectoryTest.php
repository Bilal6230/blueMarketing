<?php

namespace Tests\Unit\Finance;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class PlotReceiptGlobalVoucherDirectoryTest extends TestCase
{
    private function view(): string
    {
        return file_get_contents(resource_path('views/admin/finance/voucher/plot_receipt_workspace.blade.php'));
    }

    public function test_plot_receipt_uses_cr_dr_directory_shell_and_original_blue_palette(): void
    {
        $view = $this->view();
        foreach ([
            'bm-ui bm-voucher-directory',
            '--voucher-accent:#1769e0',
            'pr-heading', 'pr-filter-row', 'pr-table-card',
            '<x-finance.voucher-filter-card',
            '<x-finance.voucher-table-card',
            '<x-finance.voucher-column-filters />',
            'pr-table-wrap', 'pr-footer', 'prHistoryRows',
            'prLength', 'prQuickSearch', 'prFilterCustomer',
            'prFrom', 'prTo', 'prFilterSearch',
        ] as $expected) {
            $this->assertStringContainsString($expected, $view);
        }
        $this->assertStringNotContainsString('pr-hero', $view);
        $this->assertStringNotContainsString('linear-gradient(', $view);
        $this->assertNotFalse(Blade::compileString($view));
    }

    public function test_create_form_and_multi_plot_booking_allocation_are_modal_only(): void
    {
        $view = $this->view();
        $this->assertStringContainsString("@section('modal')", $view);
        $this->assertStringContainsString('id="plotReceiptCreateModal"', $view);
        $this->assertStringContainsString('data-target="#plotReceiptCreateModal"', $view);
        foreach ([
            'id="prNumber"', 'id="prDate"', 'id="prCustomer"',
            'id="prAccount"', 'id="prNarration"', 'id="prBookingRows"',
            'id="prPost"', 'id="prTotal"', 'id="prDetailsModal"',
            'id="prDetailsBody"',
        ] as $field) {
            $this->assertStringContainsString($field, $view);
        }
        $this->assertStringContainsString('rows.length>=2', $view);
        $this->assertStringContainsString("receivable_account_id:row.receivable_account_id", $view);
        $this->assertStringContainsString('request_key:pendingKey', $view);
    }

    public function test_history_uses_shared_filters_pagination_and_cr_dr_action_menu(): void
    {
        $view = $this->view();
        $this->assertStringContainsString('$.blueVoucherColumnFilters.init(', $view);
        $this->assertStringContainsString('$.blueVoucherPagination.render(', $view);
        $this->assertStringContainsString('$.blueActionMenu.enhance(', $view);
        $this->assertStringContainsString('blue-actions-cell', $view);
        $this->assertStringContainsString("$('#prHistoryRows').on('click','.pr-detail'", $view);
        $this->assertStringNotContainsString('$.blueVoucherRegisterActions.enhance(', $view);
        $this->assertStringContainsString('$(function(){', $view);
        $this->assertStringNotContainsString('document.createTextNode', $view);
        $this->assertStringContainsString('loadBookings();loadHistory();', $view);
    }

    public function test_shared_workspace_styling_is_declared_globally_not_copied_into_plot_page(): void
    {
        $global = file_get_contents(public_path('css/blue-design-system.css'));
        $view = $this->view();
        foreach ([
            '.bm-voucher-workspace .bm-voucher-filter-grid',
            '.bm-voucher-workspace .bm-voucher-table',
            '.bm-voucher-workspace .bm-voucher-table-footer',
            '.bm-voucher-workspace .bm-voucher-page-btn',
        ] as $rule) {
            $this->assertStringContainsString($rule, $global);
        }
        foreach ([
            'bm-voucher-workspace', 'bm-voucher-heading',
            'bm-voucher-filter-grid', 'bm-voucher-table-toolbar',
            'bm-voucher-table-scroll', 'bm-voucher-table-footer',
            'bm-voucher-page-btn',
        ] as $class) {
            $this->assertStringContainsString($class, $view);
        }
        $this->assertStringNotContainsString('#plotReceiptApp .pr-filter-row{display:grid', $view);
        $this->assertStringNotContainsString('#plotReceiptApp .pr-table-card{flex:', $view);
    }

    public function test_original_project_scoped_api_and_plot_print_routes_are_preserved(): void
    {
        $view = $this->view();
        foreach ([
            "route('finance.voucher.plot-receipt.options')",
            "route('finance.voucher.plot-receipt.history')",
            "route('finance.voucher.plot-receipt.post')",
            "url('admin/finance/voucher/plot-receipt')",
            "/plots/'+encodeURIComponent(v.plot_id)+'/print",
        ] as $endpoint) {
            $this->assertStringContainsString($endpoint, $view);
        }
        $this->assertStringNotContainsString("fetch(", $view);
    }
}
