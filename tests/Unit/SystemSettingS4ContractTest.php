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
        $this->assertStringContainsString('putSettings($receipt,payload', $page);
        $this->assertStringContainsString('Update Print Header?', $page);
    }

    public function test_voucher_directory_reuses_shared_user_table_controls(): void
    {
        $view=file_get_contents(resource_path('views/admin/finance/settings-voucher-display.blade.php'));
        $script=file_get_contents(public_path('js/settings-s4-voucher-display.js'));

        $this->assertStringContainsString('id="s4-directory-table"', $view);
        $this->assertStringNotContainsString('bm-modern-export-toolbar bm-export-placeholder', $view);
        $this->assertStringContainsString('blue-actions-cell', $view);
        $this->assertStringContainsString('financeVoucherComponents.createFilter', $script);
        $this->assertStringNotContainsString('blueModernDirectoryTools.mount', $script);
        $this->assertStringContainsString('blueActionMenu.enhance', $script);
        $this->assertStringContainsString('blueVoucherPagination', $script);
        $this->assertStringContainsString('merge($types)->unique()', $view);
    }

    public function test_all_supported_voucher_header_toggles_and_active_switches_exist(): void
    {
        $view=file_get_contents(resource_path('views/admin/finance/settings-voucher-display.blade.php'));
        $controller=file_get_contents(app_path('Http/Controllers/SettingController.php'));
        $script=file_get_contents(public_path('js/settings-s4-voucher-display.js'));

        $this->assertStringContainsString('VoucherAvailability::FAMILIES', $view);
        $this->assertStringContainsString('settings-receipt-toggle', $view);
        $this->assertStringContainsString('s4-availability-toggle', $view);
        $this->assertStringContainsString('data-s4-availability=', $view);
        $this->assertStringContainsString('saveVoucherAvailability(Request $request)', $controller);
        $this->assertStringContainsString('project_voucher_availability', $controller);
        $this->assertStringContainsString('allowedReceiptTypes', $controller);
        $this->assertStringContainsString('ProjectVoucherDisplay::SERIES', $controller);
        $this->assertStringContainsString('ProjectVoucherDisplay::SERIES, true) || array_key_exists', $view);

        $this->assertStringContainsString('_Receipt_Header', $controller);
        $this->assertStringContainsString('url:$tab.find(\'.s4-shell\').attr(\'data-s4-status-url\')', $script);
    }

    public function test_disabled_voucher_entry_routes_are_blocked_without_blocking_history(): void
    {
        $guard=file_get_contents(app_path('Http/Middleware/EnforceVoucherAvailability.php'));
        $routes=file_get_contents(base_path('routes/web.php'));
        $sidebar=file_get_contents(resource_path('views/admin/layouts/sidebar-menu.blade.php'));
        $migration=file_get_contents(database_path('migrations/2026_11_26_220000_create_project_voucher_availability.php'));

        $this->assertStringContainsString('EnforceVoucherAvailability::class', $routes);
        $this->assertStringContainsString('VoucherAvailability::enforce($project,$series)', $guard);
        $this->assertStringContainsString('finance.daily-vouchers.index', $guard);
        $this->assertStringContainsString('finance.voucher.bank.workspace', $guard);
        $this->assertStringContainsString('journal.voucher.index', $guard);
        $this->assertStringContainsString('ledger.store', $guard);
        $this->assertStringContainsString("query('series','CR')", $guard);
        $this->assertStringContainsString("query('series','DR')", $guard);
        $this->assertStringContainsString('booking.customer.deposit', $guard);
        $this->assertStringContainsString('finance.voucher.cash-only.store', $guard);
        $this->assertStringContainsString('finance.voucher.transfer.post', $guard);

        $this->assertStringNotContainsString('finance.voucher.bank.print', $guard);
        $this->assertStringContainsString('VoucherAvailability::enabled', $sidebar);
        $this->assertStringContainsString("Schema::create('project_voucher_availability'", $migration);
    }

    public function test_bo_br_toggle_and_remaining_voucher_prefix_editors_are_available(): void
    {
        $display = file_get_contents(app_path('Services/Finance/ProjectVoucherDisplay.php'));
        $availability = file_get_contents(app_path('Services/Finance/VoucherAvailability.php'));
        $allocator = file_get_contents(app_path('Services/Finance/ProjectVoucherAllocator.php'));
        $setting = file_get_contents(app_path('Http/Controllers/SettingController.php'));
        $view = file_get_contents(resource_path('views/admin/finance/settings-voucher-display.blade.php'));

        $this->assertStringContainsString("'BO'=>'Bank Online Voucher'", $availability);
        $this->assertStringContainsString("'BR'=>'Bank Receipt/Clearance Voucher'", $availability);
        $this->assertStringContainsString("['BO','BR']", $allocator);
        $this->assertStringContainsString('VoucherAvailability::enforce($projectId, $series)', $allocator);
        foreach (['DR','BD','BW','PP','BO','BR'] as $series) {
            $this->assertContains($series, \App\Services\Finance\ProjectVoucherDisplay::SERIES);
        }
        $this->assertStringContainsString('BD,BW,PP', $setting);
        $this->assertStringContainsString('ProjectVoucherDisplay::SERIES as $series', $view);
        $this->assertStringContainsString('data-s4-edit="{{ $series }}"', $view);
    }

    public function test_settings_export_toolbars_removed_and_switches_require_confirmation(): void
    {
        $page=file_get_contents(resource_path('views/admin/setting.blade.php'));
        $s2=file_get_contents(resource_path('views/admin/attendance/settings-tab.blade.php'));
        $s3=file_get_contents(resource_path('views/admin/finance/settings-accounts.blade.php'));
        $s4=file_get_contents(resource_path('views/admin/finance/settings-voucher-display.blade.php'));
        $script=file_get_contents(public_path('js/settings-s4-voucher-display.js'));
        $this->assertStringNotContainsString('bm-export-placeholder', $s2);
        $this->assertStringNotContainsString('bm-export-placeholder', $s3);
        $this->assertStringNotContainsString('bm-export-placeholder', $s4);
        $this->assertStringNotContainsString('Disabling a voucher hides its new-entry links', $s4);
        $this->assertStringContainsString('id="settingsVoucherToggleConfirm"', $page);
        $this->assertStringContainsString('id="settingsVoucherToggleConfirmYes"', $page);
        $this->assertStringContainsString('settingsVoucherToggleConfirmTitle', $script);
        $this->assertStringContainsString('Update Print Header?', $page);
        $this->assertStringContainsString('Deactivate Voucher?', $script);
        $this->assertStringContainsString('$toggle.prop(\'checked\',old)', $script);
    }

    public function test_s4_prefix_confirmation_no_longer_requires_sweetalert(): void
    {
        $view=file_get_contents(resource_path('views/admin/finance/settings-voucher-display.blade.php'));
        $script=file_get_contents(public_path('js/settings-s4-voucher-display.js'));

        $this->assertStringContainsString('id="s4-prefix-confirm"', $view);
        $this->assertStringContainsString('id="s4-prefix-confirm-yes"', $view);
        $this->assertStringContainsString("one('hidden.bs.modal'", $script);
        $this->assertStringNotContainsString('Confirmation dialog is unavailable', $script);
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
        $this->assertStringContainsString('method:\'POST\',data:$form.serialize()', $script);
        $this->assertStringContainsString('Numbering of issued vouchers remains unchanged.', $controller);
        $this->assertStringContainsString('return $series.\'-\'.$number;', file_get_contents(app_path('Services/Finance/ProjectVoucherDisplay.php')));
    }
}
