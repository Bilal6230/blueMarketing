<?php

namespace Tests\Unit\Finance;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class PlotPurchaseGlobalVoucherDirectoryTest extends TestCase
{
    private function view(): string
    {
        return file_get_contents(resource_path('views/admin/finance/voucher/plot_purchase_workspace.blade.php'));
    }

    public function test_plot_purchase_uses_cr_dr_shared_directory_and_original_blue_palette(): void
    {
        $view = $this->view();
        foreach ([
            'bm-voucher-directory bm-voucher-workspace',
            '--voucher-accent:#1769e0',
            '<x-finance.voucher-filter-card',
            '<x-finance.voucher-table-card',
            '<x-finance.voucher-column-filters />',
            'bm-voucher-filter-grid',
            'bm-voucher-table-scroll',
            'bm-voucher-table-footer',
            'bm-voucher-page-btn',
            'id="ppTable"',
            'id="ppFilterForm"',
            'id="ppHistory"',
            'id="ppLength"',
            'id="ppQuickSearch"',
        ] as $required) {
            $this->assertStringContainsString($required, $view);
        }
        $this->assertStringNotContainsString('pp-hero', $view);
        $this->assertStringNotContainsString('linear-gradient(', $view);
        $this->assertNotFalse(Blade::compileString($view));
    }

    public function test_purchase_specific_form_is_in_new_voucher_modal_with_all_original_fields(): void
    {
        $view = $this->view();
        $this->assertStringContainsString("@section('modal')", $view);
        $this->assertStringContainsString('id="plotPurchaseCreateModal"', $view);
        $this->assertStringContainsString('data-target="#plotPurchaseCreateModal"', $view);
        foreach ([
            'ppNumber', 'ppDate', 'ppSeller', 'ppNarration',
            'ppItems', 'ppItemTemplate', 'ppAdd',
            'ppTotal', 'ppPost', 'ppDetailsModal',
            'plot_code', 'description', 'area', 'unit',
            'road_id', 'facing_id', 'inventory_account_id',
            'unit_rate', 'is_corner', 'plot_category_definition_id',
            'plot_type_definition_id', 'plot_size_definition_id',
            'plot_unit_definition_id',
        ] as $required) {
            $this->assertStringContainsString($required, $view);
        }
    }

    public function test_purchase_history_uses_shared_global_jquery_actions_filters_and_pagination(): void
    {
        $view = $this->view();
        $this->assertStringContainsString('$.blueVoucherColumnFilters.init(', $view);
        $this->assertStringContainsString('$.blueVoucherPagination.render(', $view);
        $this->assertStringContainsString('$.blueActionMenu.enhance(', $view);
        $this->assertStringContainsString('blue-actions-cell', $view);
        $this->assertStringContainsString('ppFilterSearch', $view);
        $this->assertStringContainsString('ppSellerFilter', $view);
        $this->assertStringContainsString('ppFrom', $view);
        $this->assertStringContainsString('ppTo', $view);
        $this->assertStringContainsString('$(function(){', $view);
        $this->assertStringNotContainsString('$.blueVoucherRegisterActions.enhance(', $view);
    }

    public function test_purchase_posting_endpoints_remain_project_scoped_and_unchanged(): void
    {
        $view = $this->view();
        foreach ([
            "route('finance.voucher.plot-purchase.options')",
            "route('finance.voucher.plot-purchase.history')",
            "route('finance.voucher.plot-purchase.post')",
            "url('admin/finance/voucher/plot-purchase')",
            "X-CSRF-TOKEN",
            'request_key',
            'inventory_account_id',
            'seller_account_id',
        ] as $required) {
            $this->assertStringContainsString($required, $view);
        }
    }

    public function test_mysql_purchase_identifiers_are_short_and_receipt_tables_are_not_modified(): void
    {
        $migration=file_get_contents(base_path('database/local_pilot_migrations/2026_11_14_000200_create_v2_plot_purchase_storage.php'));
        $this->assertStringContainsString('V2IsolatedConnectionGuard::assertAllowed()', $migration);
        $this->assertStringContainsString("'v2_plot_purch_post_claim_fk'", $migration);
        $this->assertLessThanOrEqual(64, strlen('v2_plot_purch_post_claim_fk'));
        $this->assertStringNotContainsString("Schema::dropIfExists(", $migration);
        $this->assertStringNotContainsString("Schema::create('finance_plot_receipts'", $migration);
        $this->assertStringNotContainsString("Schema::table('finance_plot_receipts'", $migration);
    }
}
