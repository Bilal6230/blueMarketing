<?php

namespace Tests\Unit\Finance;

use App\Support\Finance\RolePermissionCatalog;
use App\Support\Stock\StockAbility;
use PHPUnit\Framework\TestCase;

final class Milestone02PermissionEnforcementContractTest extends TestCase
{
    private function actor(array $abilities, array $roles=[]): object
    {
        return new class($abilities,$roles) {
            public function __construct(private array $abilities,private array $roles){}
            public function can(string $ability): bool { return in_array($ability,$this->abilities,true); }
            public function hasAnyRole(array $names): bool { return (bool)array_intersect($names,$this->roles); }
        };
    }

    public function test_exactly_four_stock_modules_and_four_real_actions(): void
    {
        self::assertSame(['items','parties','entries','billing'],StockAbility::MODULES);
        self::assertSame(['view','create','edit','delete'],StockAbility::ACTIONS);
        foreach (StockAbility::MODULES as $module) {
            foreach(StockAbility::ACTIONS as $action){
                self::assertSame('stock '.$module.' '.$action,StockAbility::name($module,$action));
                self::assertSame('stock-'.$module,RolePermissionCatalog::generalModule(StockAbility::name($module,$action)));
            }
        }
        self::assertSame('Stock · Billing',RolePermissionCatalog::generalModuleLabel('stock-billing'));
    }

    public function test_new_stock_grants_are_isolated_by_workspace_and_action(): void
    {
        $actor=$this->actor(['stock billing view','stock billing create']);
        self::assertTrue(StockAbility::allows($actor,'workspace','view'));
        self::assertTrue(StockAbility::allows($actor,'billing','view'));
        self::assertTrue(StockAbility::allows($actor,'billing','create'));
        self::assertFalse(StockAbility::allows($actor,'billing','edit'));
        self::assertFalse(StockAbility::allows($actor,'items','view'));
        self::assertFalse(StockAbility::allows($actor,'entries','create'));
        self::assertFalse(StockAbility::allows($actor,'billing','delete'));
    }

    public function test_old_slip_and_admin_roles_cannot_bypass_stock_permissions(): void
    {
        foreach ([
            $this->actor(['read slip','add slip','delete slip']),
            $this->actor(['read slip','add slip','delete slip'],['admin']),
            $this->actor(['direct-update','create stock','edit stock']),
            $this->actor([],['admin']),
        ] as $actor) {
            foreach (StockAbility::MODULES as $module) {
                foreach (StockAbility::ACTIONS as $action) {
                    self::assertFalse(StockAbility::allows($actor,$module,$action),$module.' '.$action);
                }
            }
            self::assertFalse(StockAbility::allows($actor,'workspace','view'));
        }
        self::assertTrue(StockAbility::allows($this->actor([],['superadmin']),'workspace','view'));
        self::assertFalse(StockAbility::allows($this->actor([]),'workspace','view'));
    }

    public function test_stock_routes_enforce_crud_and_do_not_replace_approval_or_labour_cp(): void
    {
        $root=dirname(__DIR__,3);
        $routes=file_get_contents($root.'/routes/web.php');
        $controller=file_get_contents($root.'/app/Http/Controllers/StockController.php');
        $migration=file_get_contents($root.'/database/migrations/2026_11_24_180000_register_compact_stock_permissions.php');
        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertIsString($migration);
        foreach ([
            'stocks.items.store'=>':items,create',
            'stocks.items.update'=>':items,edit',
            'stocks.items.delete'=>':items,delete',
            'stocks.parties.store'=>':parties,create',
            'stocks.parties.update'=>':parties,edit',
            'stocks.parties.delete'=>':parties,delete',
            'stocks.entries.store'=>':entries,create',
            'stocks.bills.store'=>':billing,create',
            'stocks.bills.index'=>':billing,view',
        ] as $route=>$scope) {
            $needle="->middleware(\\App\\Http\\Middleware\\AuthorizeStockAbility::class.'".$scope."')->name('".$route."')";
            self::assertStringContainsString($needle,$routes,$route);
        }
        self::assertStringContainsString('StockAbility::allows(', $controller);
        self::assertStringNotContainsString("||\$actor->can('direct-update')", $controller);
        self::assertStringNotContainsString("||\$actor->can(\$action.' stock')", $controller);
        self::assertStringNotContainsString("auth()->user()->can('edit stock')", $controller);
        $view=file_get_contents($root.'/resources/views/admin/stock/index.blade.php');
        self::assertIsString($view);
        self::assertStringContainsString('const stockAbilities = @json($stockAbilities)', $view);
        self::assertStringContainsString("stockCan('entries','edit')", $view);
        self::assertStringContainsString("stockCan('billing','delete')", $view);
        self::assertStringContainsString("stockCan('items','edit')", $view);
        self::assertStringContainsString("stockCan('parties','delete')", $view);
        self::assertStringContainsString("if (!stockCan('billing','view')) return", $view);
        self::assertStringContainsString("name('stocks.finance.approve')", $routes);
        self::assertStringContainsString("name('labours.payment')", $routes);
        self::assertStringContainsString('Permission::findOrCreate', $migration);
        self::assertStringNotContainsString('Schema::create(', $migration);
    }

    public function test_labour_and_attendance_use_existing_permissions_without_modifying_cp_routes(): void
    {
        $routes=file_get_contents(dirname(__DIR__,3).'/routes/web.php');
        foreach ([
            'labours.index'=>'permission:read labour',
            'labours.store'=>'permission:create labour',
            'labours.update'=>'permission:update labour',
            'labours.destroy'=>'permission:delete labour',
            'labours.attendance'=>'permission:create attendance|create labour',
            'attendance.week.load'=>'permission:read attendance|read labour',
            'labours.updateRate'=>'permission:update labour',
        ] as $route=>$ability) {
            self::assertStringContainsString("middleware('".$ability."')->name('".$route."')",$routes);
        }
        foreach (['labours.payment','labours.payment.details','labours.payment.update','labours.payment.delete'] as $route) {
            self::assertStringContainsString("name('".$route."')",$routes);
        }
    }
}
