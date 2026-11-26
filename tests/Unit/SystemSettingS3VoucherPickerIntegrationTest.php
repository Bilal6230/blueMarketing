<?php

namespace Tests\Unit;

use Tests\TestCase;

final class SystemSettingS3VoucherPickerIntegrationTest extends TestCase
{
    public function test_bank_vouchers_use_bank_profile_display_titles(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Finance/VoucherController.php'));
        $view = file_get_contents(resource_path('views/admin/finance/voucher/cash_voucher.blade.php'));

        $this->assertStringContainsString('bankSettlementAccounts', $controller);
        $this->assertStringContainsString("'display_title'", $controller);
        $this->assertStringContainsString('name="bank_counter_account_id"', $view);
        $this->assertStringContainsString('$bankOption->display_title', $view);
        $this->assertStringContainsString('value="{{ $bankOption->id }}"', $view);
    }

    public function test_cash_vouchers_use_active_cash_mapping_and_display_titles(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Finance/VoucherController.php'));
        $view = file_get_contents(resource_path('views/admin/finance/voucher/cash_voucher.blade.php'));
        $writer = file_get_contents(app_path('Http/Controllers/Finance/CashOnlyVoucherController.php'));

        $this->assertStringContainsString('cashSettlementAccounts', $controller);
        $this->assertStringContainsString("'project_cash_accounts'", $controller);
        $this->assertStringContainsString('value="{{ $cashOption->account_mapping_id }}"', $view);
        $this->assertStringContainsString('{{ $cashOption->display_title }}', $view);
        $this->assertStringContainsString('Select an active configured Cash Account', $writer);
        $this->assertStringContainsString('lockForUpdate()->exists()', $writer);
    }
}
