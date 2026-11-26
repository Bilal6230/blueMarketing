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

    public function test_s3_modal_form_is_not_extracted_by_generic_settings_modalizer(): void
    {
        $page = file_get_contents(resource_path('views/admin/setting.blade.php'));
        $s3 = file_get_contents(resource_path('views/admin/finance/settings-accounts.blade.php'));
        $this->assertStringContainsString(':not([data-ajax="off"])', $page);
        $this->assertStringContainsString(':not(#s3-account-form)', $page);
        $this->assertStringContainsString('id="s3-account-modal"', $s3);
        $this->assertStringContainsString('id="s3-account-form"', $s3);
        $this->assertStringContainsString('data-ajax="off"', $s3);
        $this->assertStringContainsString('data-bank-url=', $s3);
        $this->assertStringContainsString('data-cash-url=', $s3);
    }

    public function test_s3_head_and_subhead_selectors_keep_existing_project_mapping_identity(): void
    {
        $view = file_get_contents(resource_path('views/admin/finance/settings-accounts.blade.php'));
        $script = file_get_contents(public_path('js/settings-s3-accounts.js'));

        $this->assertStringContainsString('id="s3-head-account"', $view);
        $this->assertStringContainsString('id="s3-subhead-account"', $view);
        $this->assertStringContainsString('data-head-id="{{ $mapping->head_accounting_id }}"', $view);
        $this->assertStringContainsString('data-subhead-id="{{ $mapping->subhead_accounting_id }}"', $view);
        $this->assertStringContainsString('value="{{ $mapping->id }}"', $view);
        $this->assertStringContainsString('type="hidden" name="account_mapping_id"', $view);
        $this->assertStringContainsString('function populateSubheads(headId,chosenMapping)', $script);
        $this->assertStringContainsString("dropdownParent:'body'", $script);
        $this->assertStringContainsString('subheadTs.addOptions(', $script);
        $this->assertStringContainsString('subheadTs.setValue(requested,true)', $script);
        $this->assertStringContainsString("selectMapping(account.account_mapping_id)", $script);
        $this->assertStringContainsString("headTs=new TomSelect", $script);
        $this->assertStringContainsString("subheadTs=new TomSelect", $script);
        $this->assertStringContainsString("$mapping.val($subhead.val()||'')", $script);
        $this->assertStringContainsString("if(!$mapping.val())", $script);
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
