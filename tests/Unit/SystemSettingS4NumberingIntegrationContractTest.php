<?php

namespace Tests\Unit;

use Tests\TestCase;

final class SystemSettingS4NumberingIntegrationContractTest extends TestCase
{
    public function test_one_new_table_and_no_ledger_prefix_column(): void
    {
        $migration=file_get_contents(database_path('migrations/2026_11_28_120000_create_project_voucher_prefix_counters.php'));
        $this->assertStringContainsString("Schema::create('project_voucher_prefix_counters'", $migration);
        $this->assertStringContainsString("unique(['project_id','series','prefix']", $migration);
        $this->assertStringContainsString("'has_history'", $migration);
        $this->assertStringNotContainsString("Schema::table('customer_ledger'", $migration);
        $this->assertStringNotContainsString("Schema::table('ledgers'", $migration);
    }

    public function test_settings_prefix_lookup_and_freeze_are_project_scoped(): void
    {
        $controller=file_get_contents(app_path('Http/Controllers/SettingController.php'));
        $routes=file_get_contents(base_path('routes/web.php'));
        $view=file_get_contents(resource_path('views/admin/finance/settings-voucher-display.blade.php'));
        $script=file_get_contents(public_path('js/settings-s4-voucher-display.js'));
        $service=file_get_contents(app_path('Services/Finance/ProjectVoucherPrefixCounter.php'));
        $this->assertStringContainsString('function previewVoucherPrefix(Request $request)', $controller);
        $this->assertStringContainsString("DB::table('projects')->where('id',$projectId)->lockForUpdate()", $controller);
        $this->assertStringContainsString('$service->configure($projectId,$data[', $controller);
        $this->assertStringContainsString('project_voucher_prefix_counters', $service);
        $this->assertStringContainsString("'issued_count'", $service);
        $this->assertStringContainsString("'has_history'", $service);
        $this->assertStringContainsString('setting.voucher-prefix.preview', $routes);
        $this->assertStringContainsString('data-s4-prefix-preview-url=', $view);
        $this->assertStringContainsString('name="start_number"', $view);
        $this->assertStringContainsString('lookupPrefix()', $script);
        $this->assertStringContainsString('!!data.existing', $script);
    }

    public function test_issued_identity_is_permanent_and_cash_form_retains_canonical_source(): void
    {
        $identity=file_get_contents(app_path('Services/Finance/ProjectVoucherDisplayIdentity.php'));
        $controller=file_get_contents(app_path('Http/Controllers/Finance/VoucherController.php'));
        $form=file_get_contents(resource_path('views/admin/finance/voucher/cash_voucher.blade.php'));
        $directory=file_get_contents(app_path('Http/Controllers/Finance/DailyVoucherDirectoryController.php'));
        $this->assertStringContainsString('return $identity ? (string)$identity->display_number : null;', $identity);
        $this->assertStringContainsString('->issue($projectId,$series,$sourceKind,$sourceId,$date,$display)', $identity);
        $this->assertStringContainsString("'display_voucher_number'=>$display", $controller);
        $this->assertStringContainsString('id="display_voucher_number"', $form);
        $this->assertStringContainsString('name="voucher_number" id="voucher_number"', $form);
        $this->assertStringContainsString('const DISPLAY_PREFIX =', $form);
        $this->assertStringContainsString("->where('dn.display_number','like',$term)", $directory);
    }
}
