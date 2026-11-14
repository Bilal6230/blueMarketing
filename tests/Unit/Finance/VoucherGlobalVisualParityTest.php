<?php

namespace Tests\Unit\Finance;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class VoucherGlobalVisualParityTest extends TestCase
{
    public function test_shared_themed_directory_css_preserves_existing_series_palettes(): void
    {
        $css=file_get_contents(public_path('css/blue-design-system.css'));
        $this->assertStringContainsString('.bm-ui.bm-voucher-directory', $css);
        $this->assertStringContainsString('--voucher-accent', $css);
        $this->assertStringContainsString('.daily-voucher-page.bm-voucher-directory', $css);
        $this->assertStringContainsString('.bm-ui.bm-plot-workspace', $css);
        $this->assertStringContainsString('.main-content.page-content:has(> .bm-plot-workspace)', $css);
        $this->assertStringNotContainsString('--bm-success:var(--voucher-accent)', $css);
    }

    public function test_original_journal_and_commission_registers_keep_blue_theme(): void
    {
        $journal=file_get_contents(resource_path('views/admin/reports/vouchers/index.blade.php'));
        $commission=file_get_contents(resource_path('views/admin/reports/vouchers/commision_index.blade.php'));
        $this->assertStringContainsString('--voucher-accent:#087cfa', $journal);
        $this->assertStringContainsString('--voucher-accent:#087cfa', $commission);
        $this->assertStringContainsString('background:#007bff!important;color:#fff!important', $commission);
        foreach([$journal,$commission] as $view) {
            $this->assertStringContainsString('<x-finance.voucher-filter-card', $view);
            $this->assertStringContainsString('<x-finance.voucher-table-card', $view);
            $this->assertStringContainsString('$.blueVoucherRegisterActions.enhance(', $view);
            $this->assertNotFalse(Blade::compileString($view));
        }
    }

    public function test_cr_dr_bd_bw_keep_credit_debit_theme_and_existing_v2_routes(): void
    {
        $daily=file_get_contents(resource_path('views/admin/finance/voucher/daily_directory.blade.php'));
        $transfer=file_get_contents(resource_path('views/admin/finance/voucher/bank_transfer_workspace.blade.php'));
        $this->assertStringContainsString('dv-theme-credit', $daily);
        $this->assertStringContainsString('dv-theme-debit', $daily);
        $this->assertStringContainsString('bt-credit', $transfer);
        $this->assertStringContainsString('bt-debit', $transfer);
        $this->assertStringContainsString('bm-voucher-directory bt-page', $transfer);
        $this->assertStringContainsString("route('finance.voucher.transfer.post'", $transfer);
        $this->assertStringContainsString('request_key:pendingRequestKey', $transfer);
    }

    public function test_original_plot_and_extra_charge_vouchers_reuse_shared_listings_without_rewriting_forms(): void
    {
        foreach([
            'admin/booking/receive.blade.php',
            'admin/booking/extra_charge.blade.php'
        ] as $name){
            $view=file_get_contents(resource_path('views/'.$name));
            $this->assertStringContainsString('bm-booking-vouchers', $view);
            $this->assertStringContainsString('--voucher-accent:#7145bd', $view);
            $this->assertStringContainsString('<x-finance.voucher-table-card', $view);
            $this->assertStringContainsString('</x-finance.voucher-table-card>', $view);
            $this->assertStringContainsString("route('booking.customer.deposit')", $view);
        }
        foreach([
            'admin/finance/voucher/plot_receipt_workspace.blade.php' => 'bm-voucher-directory',
            'admin/finance/voucher/plot_purchase_workspace.blade.php' => 'bm-plot-workspace',
        ] as $name => $expectedShell){
            $view=file_get_contents(resource_path('views/'.$name));
            $this->assertStringContainsString($expectedShell, $view);
            $this->assertStringContainsString('<x-finance.voucher-table-card', $view);
            $this->assertNotFalse(Blade::compileString($view));
        }
    }

    public function test_plot_and_bank_linked_history_reuse_original_actions_through_shared_menu(): void
    {
        foreach([
            'admin/finance/voucher/plot_receipt_workspace.blade.php',
            'admin/finance/voucher/plot_purchase_workspace.blade.php',
            'admin/finance/voucher/bank_linked_workspace.blade.php',
        ] as $name) {
            $view=file_get_contents(resource_path('views/'.$name));
            $this->assertStringContainsString('$.blueVoucherRegisterActions.enhance(', $view);
            $this->assertStringContainsString('<x-finance.voucher-table-card', $view);
        }
    }

    public function test_shared_action_adapter_is_globally_loaded_and_preserves_original_handlers(): void
    {
        $master=file_get_contents(resource_path('views/admin/layouts/master.blade.php'));
        $script=file_get_contents(public_path('js/blue-voucher-register-actions.js'));
        $this->assertSame(1, substr_count($master,'blue-voucher-register-actions.js'));
        $this->assertStringContainsString('$.blueActionMenu.enhance', $script);
        $this->assertStringContainsString("$(document).on('draw.dt'", $script);
        $this->assertStringContainsString('$items.detach()', $script);
        $this->assertStringNotContainsString('.trigger(\'click\')', $script);
    }
}
