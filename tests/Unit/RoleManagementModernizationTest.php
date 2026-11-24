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

    public function test_existing_application_permissions_are_grouped_into_separate_modules(): void
    {
        $expected=[
            'read attendance'=>'attendance',
            'create payroll'=>'payroll',
            'read labour'=>'labour',
            'update booking price'=>'booking',
            'view booking report'=>'booking',
            'read landing page'=>'landing',
            'update plot number'=>'plot',
            'stock module'=>'stock',
            'labour module'=>'labour',
            'delete plot'=>'plot',
            'read role'=>'role',
            'create permission'=>'permission',
            'read user'=>'user',
            'read setting'=>'setting',
            'read report'=>'report',
            'lead details'=>'lead',
            'lead search'=>'lead',
            'send sms'=>'sms',
            'filemanager'=>'filemanager',
            'add slip'=>'slip',
            'bank posting'=>'bank',
            'pass cheque'=>'cheque',
            'party_reprot'=>'report',
            'report cashbook'=>'report',
            'report party_ledger'=>'report',
            'report party_report'=>'report',
            'plot voucher'=>'plot',
            'plot voucher form'=>'plot',
            'plot voucher report'=>'plot',
            'print voucher'=>'voucher',
            'view inventory'=>'inventory',
        ];
        foreach($expected as $name=>$module){
            self::assertSame($module,RolePermissionCatalog::generalModule($name),$name);
        }
        self::assertSame('other',RolePermissionCatalog::generalModule('unrecognized special ability'));
        self::assertSame('Settings',RolePermissionCatalog::generalModuleLabel('setting'));
        self::assertSame('Expenses',RolePermissionCatalog::generalModuleLabel('expence'));
        self::assertSame('Other Access',RolePermissionCatalog::generalModuleLabel('other'));
        self::assertTrue(RolePermissionCatalog::isSelectableFinance('finance cr create'));
        self::assertTrue(RolePermissionCatalog::isSelectableFinance('finance plot-purchase reverse'));
        self::assertFalse(RolePermissionCatalog::isSelectableFinance('finance jv reverse'));
        self::assertFalse(RolePermissionCatalog::isSelectableFinance('finance ccr reverse'));
        self::assertFalse(RolePermissionCatalog::isSelectableFinance('finance cr withdraw'));
    }

    public function test_only_supported_reverse_actions_are_selectable(): void
    {
        foreach (['jv','sv','cv','ccr','cdr','bor','bop'] as $module) {
            self::assertNotContains('reverse',RolePermissionCatalog::moduleActions($module),$module);
        }
        foreach (['cr','dr','cp','bank-deposit','bank-withdrawal','plot-receipt','plot-purchase'] as $module) {
            self::assertContains('reverse',RolePermissionCatalog::moduleActions($module),$module);
        }
    }

    public function test_role_editor_has_module_navigation_and_preserves_hidden_grants(): void
    {
        $root=dirname(__DIR__,2);
        $view=file_get_contents($root.'/resources/views/admin/role.blade.php');
        $controller=file_get_contents($root.'/app/Http/Controllers/RoleController.php');
        self::assertIsString($view);
        self::assertIsString($controller);
        self::assertStringContainsString("id:'general:'+module", $view);
        self::assertStringContainsString("group:'application'", $view);
        self::assertStringContainsString("OTHER MODULES", $view);
        self::assertStringContainsString("FINANCE & DAILY ENTRIES", $view);
        self::assertStringContainsString("p.module_label||module", $view);
        self::assertStringContainsString("RolePermissionCatalog::generalModule(", $controller);
        self::assertStringContainsString("RolePermissionCatalog::generalModuleLabel(", $controller);
        self::assertStringContainsString("RolePermissionCatalog::moduleActions(\$module)", $controller);
        self::assertStringContainsString("array_merge(\$available,\$hidden)", $controller);
        self::assertStringNotContainsString("sections.push({id:'general',title:'Other Modules'", $view);
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
        self::assertStringContainsString('hidden_with_active_grants',$audit);
        self::assertStringContainsString('finance_unexposed',$audit);
        self::assertStringContainsString('->lockForUpdate()',$controller);
        self::assertStringContainsString('$role->delete()',$controller);
        self::assertStringNotContainsString('forceDelete',$controller);
        self::assertStringContainsString("hasColumn('roles','updated_by')",$controller);
        self::assertStringContainsString("hasColumn('roles','updated_by')",$migration);
        self::assertStringContainsString("permissions:audit",$audit);
        self::assertStringNotContainsString('DB::table(\'permissions\')->delete()', $audit);
    }
}
