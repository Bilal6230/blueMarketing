<?php

namespace Tests\Unit;

use App\Support\Finance\RolePermissionCatalog;
use App\Support\Finance\FinanceActionPermission;
use PHPUnit\Framework\TestCase;

final class RoleManagementModernizationTest extends TestCase
{
    public function test_legacy_generic_voucher_permissions_are_not_selectable_duplicates(): void
    {
        foreach (RolePermissionCatalog::LEGACY_VOUCHER_NAMES as $name) {
            self::assertSame('legacy',RolePermissionCatalog::category($name));
            self::assertTrue(RolePermissionCatalog::isLegacyVoucher($name));
        }
        self::assertSame('finance',RolePermissionCatalog::category('finance cr create'));
        self::assertSame('general',RolePermissionCatalog::category('create role'));
    }

    public function test_each_module_has_unique_relevant_actions(): void
    {
        foreach (FinanceActionPermission::MODULES as $module) {
            $actions = RolePermissionCatalog::moduleActions($module);
            self::assertSame(count($actions),count(array_unique($actions)), $module.' has duplicate actions');
            foreach ($actions as $action) {
                self::assertContains($action, FinanceActionPermission::ACTIONS, $module);
                self::assertSame('finance '.$module.' '.$action,
                    FinanceActionPermission::name($module,$action));
            }
        }
        self::assertNotContains('withdraw',RolePermissionCatalog::moduleActions('jv'));
        self::assertNotContains('approve',RolePermissionCatalog::moduleActions('draft'));
    }

    public function test_role_page_only_has_requested_six_columns_and_ajax_modals(): void
    {
        $root = dirname(__DIR__,2);
        $view = file_get_contents($root.'/resources/views/admin/role.blade.php');
        self::assertIsString($view);
        foreach (['ID','Name','Guard','Updated','Updated By','Actions'] as $label) {
            self::assertStringContainsString($label,$view);
        }
        self::assertStringNotContainsString('<th>Permission</th>',$view);
        self::assertStringNotContainsString('datatable',$view);
        self::assertStringContainsString('$.blueActionMenu.enhance',$view);
        self::assertStringContainsString('$.blueVoucherPagination.render',$view);
        self::assertStringContainsString('roleColumnFilter',$view);
        self::assertStringContainsString('rolePermissionSearch',$view);
        self::assertStringContainsString('roleDeleteModal',$view);
        self::assertStringContainsString('X-Requested-With',$view);
        self::assertStringContainsString('data-ajax="off"',$view);
    }

    public function test_backend_preserves_existing_legacy_grants_and_archives_roles(): void
    {
        $root = dirname(__DIR__,2);
        $controller = file_get_contents($root.'/app/Http/Controllers/RoleController.php');
        $migration = file_get_contents($root.'/database/migrations/2026_11_18_140000_add_updated_by_to_roles.php');
        $audit = file_get_contents($root.'/app/Console/Commands/AuditRolePermissions.php');
        self::assertStringContainsString('RolePermissionCatalog::isLegacyVoucher',$controller);
        self::assertStringContainsString('->syncPermissions(',$controller);
        self::assertStringContainsString('->lockForUpdate()',$controller);
        self::assertStringContainsString('$role->delete()',$controller);
        self::assertStringNotContainsString('forceDelete',$controller);
        self::assertStringContainsString("hasColumn('roles','updated_by')",$controller);
        self::assertStringContainsString("hasColumn('roles','updated_by')",$migration);
        self::assertStringContainsString("permissions:audit",$audit);
        self::assertStringNotContainsString('DB::table(\'permissions\')->delete()', $audit);
    }
}
