<?php

namespace Tests\Unit;

use Tests\TestCase;

final class SystemSettingS3ContractTest extends TestCase
{
    public function test_s3_has_separate_full_height_bank_cash_cards_and_editors(): void
    {
        $view=file_get_contents(resource_path('views/admin/finance/settings-accounts.blade.php'));
        $this->assertStringContainsString('id="s3-bank-table"', $view);
        $this->assertStringContainsString('id="s3-cash-table"', $view);
        $this->assertStringContainsString('settings-fill-tab', $view);
        $this->assertStringContainsString('data-s3-new="bank"', $view);
        $this->assertStringContainsString('data-s3-new="cash"', $view);
        $this->assertStringContainsString('id="s3-account-modal"', $view);
        $this->assertStringContainsString('name="display_title"', $view);
        $this->assertStringNotContainsString('Recent bank account changes', $view);
        $this->assertStringNotContainsString('Payment method labels', $view);
    }

    public function test_s3_reuses_modern_tables_and_preserves_ajax_form_ownership(): void
    {
        $view=file_get_contents(resource_path('views/admin/finance/settings-accounts.blade.php'));
        $script=file_get_contents(public_path('js/settings-s3-accounts.js'));
        $this->assertStringContainsString('bm-modern-export-toolbar', $view);
        $this->assertStringContainsString('blue-actions-cell', $view);
        $this->assertStringContainsString('financeVoucherComponents.createFilter', $script);
        $this->assertStringContainsString('blueActionMenu.enhance', $script);
        $this->assertStringContainsString('blueModernDirectoryTools.mount', $script);
        $this->assertStringContainsString('e.preventDefault()', str_replace('event.preventDefault()', 'e.preventDefault()', $script));
        $this->assertStringContainsString('data-ajax="off"', $view);
    }

    public function test_s3_cash_bank_records_are_project_scoped_audited_and_credentials_not_disclosed(): void
    {
        $controller=file_get_contents(app_path('Http/Controllers/SettingController.php'));
        $model=file_get_contents(app_path('Models/ProjectBankAccount.php'));
        $routes=file_get_contents(base_path('routes/web.php'));
        $this->assertStringContainsString('public function saveProjectCashAccount(', $controller);
        $this->assertStringContainsString('public function changeProjectAccountStatus(', $controller);
        $this->assertStringContainsString('public function accountSettingsDetail(', $controller);
        $this->assertStringContainsString('where(\'project_id\', $projectId)', $controller);
        $this->assertStringContainsString('project_cash_account_audits', $controller);
        $this->assertStringContainsString('project_bank_account_audits', $controller);
        $this->assertStringContainsString('account_number_last4', $controller);
        $this->assertStringContainsString("'display_title'", $model);
        $this->assertStringContainsString("->name('setting.cash-account.save')", $routes);
    }

    public function test_s3_migration_preserves_original_bank_records(): void
    {
        $migration=file_get_contents(database_path('migrations/2026_11_26_210000_add_s3_bank_cash_account_masters.php'));
        $this->assertStringContainsString("Schema::table('project_bank_accounts'", $migration);
        $this->assertStringContainsString("Schema::create('project_cash_accounts'", $migration);
        $this->assertStringContainsString("Schema::create('project_cash_account_audits'", $migration);
        $this->assertStringNotContainsString('Schema::dropIfExists(\'project_bank_accounts\')', $migration);
    }
}
