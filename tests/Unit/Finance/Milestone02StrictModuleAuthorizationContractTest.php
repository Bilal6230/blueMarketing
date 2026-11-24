<?php

namespace Tests\Unit\Finance;

use App\Support\Finance\RolePermissionCatalog;
use App\Support\Stock\StockAbility;
use PHPUnit\Framework\TestCase;

final class Milestone02StrictModuleAuthorizationContractTest extends TestCase
{
    private function actor(array $abilities, array $roles=[]): object
    {
        return new class($abilities,$roles) {
            public function __construct(private array $abilities,private array $roles){}
            public function can(string $name): bool { return in_array($name,$this->abilities,true); }
            public function hasAnyRole(array $names): bool { return (bool)array_intersect($names,$this->roles); }
        };
    }

    public function test_stock_needs_exact_independent_grants_even_if_legacy_slip_and_admin_are_present(): void
    {
        $legacy=$this->actor(['read slip','add slip','delete slip','direct-update','create stock','edit stock'],['admin']);
        foreach (StockAbility::MODULES as $module) {
            foreach (StockAbility::ACTIONS as $action) {
                self::assertFalse(StockAbility::allows($legacy,$module,$action));
            }
        }
        self::assertFalse(StockAbility::allows($legacy,'workspace','view'));
        $granted=$this->actor(['stock items view','stock billing create']);
        self::assertTrue(StockAbility::allows($granted,'items','view'));
        self::assertTrue(StockAbility::allows($granted,'workspace','view'));
        self::assertTrue(StockAbility::allows($granted,'billing','create'));
        self::assertFalse(StockAbility::allows($granted,'billing','view'));
        self::assertFalse(StockAbility::allows($granted,'items','edit'));
        self::assertTrue(StockAbility::allows($this->actor([],['superadmin']),'entries','delete'));
    }

    public function test_old_stock_direct_update_and_import_bypasses_are_gone(): void
    {
        $controller=file_get_contents(dirname(__DIR__,3).'/app/Http/Controllers/StockController.php');
        self::assertIsString($controller);
        self::assertStringNotContainsString("->can('edit stock')",$controller);
        self::assertStringNotContainsString("->can('direct-update')",$controller);
        self::assertStringNotContainsString("->can(\$action.' stock')",$controller);
        self::assertStringContainsString(
            "StockAbility::allows(auth()->user(),'items','edit')",$controller
        );
        self::assertStringContainsString("if (!\$canItems) \$items = collect()", $controller);
        self::assertStringContainsString("if (!\$canParties) \$parties = collect()", $controller);
    }

    public function test_employee_tasks_are_a_real_module_with_view_and_independent_crud(): void
    {
        $root=dirname(__DIR__,3);
        $routes=file_get_contents($root.'/routes/web.php');
        $sidebar=file_get_contents($root.'/resources/views/admin/layouts/sidebar-menu.blade.php');
        $view=file_get_contents($root.'/resources/views/admin/employee-tasks/index.blade.php');
        $controller=file_get_contents($root.'/app/Http/Controllers/EmployeeTaskController.php');
        $migration=file_get_contents($root.'/database/migrations/2026_11_24_190000_register_employee_task_permissions.php');
        foreach(['read','create','update','delete'] as $action) {
            self::assertSame('employee-tasks',RolePermissionCatalog::generalModule($action.' employee task'));
            self::assertStringContainsString($action.' employee task',$migration);
            self::assertStringContainsString("->can(\$action.' employee task')",$controller);
        }
        self::assertStringContainsString("@can('read employee task')",$sidebar);
        foreach ([
            'employee-tasks.index'=>'read employee task',
            'employee-tasks.store'=>'create employee task',
            'employee-tasks.update'=>'update employee task',
            'employee-tasks.destroy'=>'delete employee task',
        ] as $route=>$permission) {
            self::assertStringContainsString(
                "middleware('permission:".$permission."')->name('".$route."')",$routes
            );
        }
        self::assertStringContainsString('@if($canCreate)',$view);
        self::assertStringContainsString('@if($canUpdate)',$view);
        self::assertStringContainsString('@if($canDelete)',$view);
        self::assertStringContainsString('$employeeTask->assigned_to===auth()->id()',$controller);
        self::assertStringContainsString("givePermissionTo('read employee task')",$migration);
        self::assertStringNotContainsString('Schema::create(',$migration);
    }

    public function test_sidebar_view_rights_match_stock_finance_and_labour_routes(): void
    {
        $root=dirname(__DIR__,3);
        $view=file_get_contents($root.'/resources/views/admin/layouts/sidebar-menu.blade.php');
        self::assertStringContainsString("StockAbility::allows(auth()->user(),'workspace','view')",$view);
        self::assertStringContainsString("@can('read labour')",$view);
        self::assertStringContainsString("FinanceActionPermission::name(\$module,'view')",$view);
        self::assertStringNotContainsString("@can('stock module')",$view);
        self::assertStringNotContainsString("@can('labour module')",$view);
    }
}
