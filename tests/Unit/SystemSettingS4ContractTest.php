<?php

namespace Tests\Unit;

use Tests\TestCase;

final class SystemSettingS4ContractTest extends TestCase
{
    public function test_voucher_display_uses_full_names_and_one_modern_listing(): void
    {
        $page=file_get_contents(resource_path('views/admin/setting.blade.php'));
        $view=file_get_contents(resource_path('views/admin/finance/settings-voucher-display.blade.php'));
        $definition=file_get_contents(app_path('Services/Finance/ProjectVoucherDisplay.php'));

        $this->assertStringContainsString("@include('admin.finance.settings-voucher-display')", $page);
        $this->assertStringContainsString('settings-fill-tab', $view);
        $this->assertStringContainsString('settings-fill-scroll', $view);
        $this->assertStringContainsString('ProjectVoucherDisplay::label($series)', $view);
        $this->assertStringContainsString("'CR'=>'Cash Receipt Voucher'", $definition);
        $this->assertStringContainsString("'BOR'=>'Bank Receipt Voucher'", $definition);
        $this->assertStringContainsString("'CCR'=>'Cash Credit Voucher'", $definition);
        $this->assertStringContainsString('data-s4-series=', $view);
        $this->assertStringNotContainsString('Custom payment methods', $view);
    }

    public function test_print_headers_remain_in_voucher_rows_with_existing_ajax_safety(): void
    {
        $view=file_get_contents(resource_path('views/admin/finance/settings-voucher-display.blade.php'));
        $page=file_get_contents(resource_path('views/admin/setting.blade.php'));

        $this->assertStringContainsString('id="settings-receipt-header-form"', $view);
        $this->assertStringContainsString('settings-receipt-toggle', $view);
        $this->assertStringContainsString('name="toggles[{{ $keyType }}]"', $view);
        $this->assertStringContainsString("putSettings($receipt,payload", $page);
        $this->assertStringContainsString('Update Print Header?', $page);
    }

    public function test_modal_saves_prefix_without_pretending_start_number_is_ready(): void
    {
        $view=file_get_contents(resource_path('views/admin/finance/settings-voucher-display.blade.php'));
        $script=file_get_contents(public_path('js/settings-s4-voucher-display.js'));
        $controller=file_get_contents(app_path('Http/Controllers/SettingController.php'));

        $this->assertStringContainsString('id="s4-display-modal"', $view);
        $this->assertStringContainsString('id="s4-display-form"', $view);
        $this->assertStringContainsString('data-ajax="off"', $view);
        $this->assertStringContainsString('id="s4-start-number"', $view);
        $this->assertStringContainsString('disabled', $view);
        $this->assertStringContainsString("method:'POST',data:$form.serialize()", $script);
        $this->assertStringContainsString('Numbering of issued vouchers remains unchanged.', $controller);
        $this->assertStringContainsString("return $series.'-'.$number;", $controller = file_get_contents(app_path('Services/Finance/ProjectVoucherDisplay.php')));
    }
}
