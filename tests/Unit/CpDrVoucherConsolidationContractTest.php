<?php

namespace Tests\Unit;

use App\Support\Finance\FinanceActionPermission;
use App\Services\Finance\VoucherAvailability;
use PHPUnit\Framework\TestCase;

final class CpDrVoucherConsolidationContractTest extends TestCase
{
    public function test_old_cp_series_maps_to_dr_authorization_without_changing_ledger_records(): void
    {
        self::assertSame('dr', FinanceActionPermission::moduleForSeries('CP'));
        self::assertSame('dr', FinanceActionPermission::moduleForSeries('DR'));
        $controller = file_get_contents(dirname(__DIR__,2).'/app/Http/Controllers/Finance/VoucherController.php');
        self::assertStringContainsString("if (!\$incoming && \$series === 'CP') \$series = 'DR';", $controller);
        self::assertStringContainsString("'CP' => 'Debit Voucher'", $controller);
    }

    public function test_roles_show_only_dr_and_preserve_both_underlying_action_names(): void
    {
        $role = file_get_contents(dirname(__DIR__,2).'/resources/views/admin/role.blade.php');
        self::assertStringContainsString("m.module==='cp')return;", $role);
        self::assertStringContainsString("m.module==='dr'?['dr','cp']", $role);
        self::assertStringContainsString('byAction[action].names.push(permissionName)', $role);
        self::assertStringContainsString("p.names.every(function(n){return selected.has(n);})", $role);
    }

    public function test_voucher_settings_only_display_dr_but_keep_cp_for_historical_read_paths(): void
    {
        $view = file_get_contents(dirname(__DIR__,2).'/resources/views/admin/finance/settings-voucher-display.blade.php');
        $availability = file_get_contents(dirname(__DIR__,2).'/app/Services/Finance/VoucherAvailability.php');
        $directory = file_get_contents(dirname(__DIR__,2).'/app/Http/Controllers/Finance/DailyVoucherDirectoryController.php');

        self::assertStringContainsString("reject(fn(\$series) => \$series === 'CP')", $view);
        self::assertStringContainsString("@continue(\$series === 'CP')", $view);
        self::assertArrayNotHasKey('CP', VoucherAvailability::FAMILIES);
        self::assertStringContainsString("=== 'CP' ? 'DR'", $availability);
        self::assertStringContainsString("'DR' => [['DR','CP']", $directory);
    }

    public function test_grants_migration_never_rewrites_vouchers_or_removes_permissions(): void
    {
        $migration = file_get_contents(dirname(__DIR__,2).'/database/migrations/2026_11_26_230000_consolidate_legacy_cp_dr_role_grants.php');
        self::assertStringContainsString('role_has_permissions', $migration);
        self::assertStringContainsString('model_has_permissions', $migration);
        self::assertStringContainsString('insertOrIgnore', $migration);
        self::assertStringNotContainsString('DELETE FROM', $migration);
        self::assertStringNotContainsString("DB::table('ledgers')", $migration);
        self::assertStringNotContainsString("DB::table('customer_ledger')", $migration);
    }
}
