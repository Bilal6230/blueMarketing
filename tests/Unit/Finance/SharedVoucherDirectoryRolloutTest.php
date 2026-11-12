<?php

namespace Tests\Unit\Finance;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class SharedVoucherDirectoryRolloutTest extends TestCase
{
    public function test_global_assets_are_loaded_once_from_master_layout(): void
    {
        $master=file_get_contents(resource_path('views/admin/layouts/master.blade.php'));
        foreach(['blue-design-system.css','blue-action-menu.js','blue-voucher-pagination.js','blue-voucher-column-filter.js'] as $asset){
            $this->assertSame(1,substr_count($master,$asset),$asset.' should be loaded exactly once');
        }
    }

    public function test_cr_dr_jv_cv_and_bank_transfer_directories_use_shared_card_components(): void
    {
        foreach([
            'admin/finance/voucher/daily_directory.blade.php',
            'admin/finance/voucher/bank_transfer_workspace.blade.php',
            'admin/reports/vouchers/index.blade.php',
            'admin/reports/vouchers/commision_index.blade.php',
            'admin/finance/voucher/cash_voucher.blade.php',
        ] as $view){
            $contents=file_get_contents(resource_path('views/'.$view));
            $this->assertStringContainsString('<x-finance.voucher-filter-card',$contents,$view);
            $this->assertStringContainsString('</x-finance.voucher-filter-card>',$contents,$view);
            $this->assertStringContainsString('<x-finance.voucher-table-card',$contents,$view);
            $this->assertStringContainsString('</x-finance.voucher-table-card>',$contents,$view);
            $this->assertNotFalse(Blade::compileString($contents),$view);
        }
    }

    public function test_additional_finance_voucher_listing_workspaces_use_shared_table_card(): void
    {
        foreach([
            'admin/finance/voucher/bank_linked_workspace.blade.php',
            'admin/finance/voucher/plot_receipt_workspace.blade.php',
            'admin/finance/voucher/plot_purchase_workspace.blade.php',
        ] as $view){
            $contents=file_get_contents(resource_path('views/'.$view));
            $this->assertStringContainsString('<x-finance.voucher-table-card',$contents,$view);
            $this->assertStringContainsString('</x-finance.voucher-table-card>',$contents,$view);
            $this->assertNotFalse(Blade::compileString($contents));
        }
    }

    public function test_cr_dr_and_bd_bw_reuse_the_same_excel_popover_filter_and_paginator(): void
    {
        $daily=file_get_contents(resource_path('views/admin/finance/voucher/daily_directory.blade.php'));
        $bank=file_get_contents(resource_path('views/admin/finance/voucher/bank_transfer_workspace.blade.php'));
        foreach([$daily,$bank] as $contents){
            $this->assertStringContainsString('<x-finance.voucher-column-filters />',$contents);
            $this->assertStringContainsString('$.blueVoucherColumnFilters.init(', $contents);
            $this->assertStringContainsString('$.blueVoucherPagination.render(', $contents);
        }
        $filter=file_get_contents(public_path('js/blue-voucher-column-filter.js'));
        $pagination=file_get_contents(public_path('js/blue-voucher-pagination.js'));
        $this->assertStringContainsString('filterRows', $filter);
        $this->assertStringContainsString('queryParams', $filter);
        $this->assertStringContainsString('aria-current', $pagination);
    }

    public function test_shared_ui_rollout_does_not_change_finance_posting_handlers(): void
    {
        $bank=file_get_contents(resource_path('views/admin/finance/voucher/bank_transfer_workspace.blade.php'));
        $this->assertStringContainsString("route('finance.voucher.transfer.post'", $bank);
        $this->assertStringContainsString('request_key:pendingRequestKey',$bank);
        $this->assertStringContainsString('$.blueActionMenu.enhance',$bank);
    }
}
