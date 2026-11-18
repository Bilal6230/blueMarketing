<?php

namespace Tests\Unit\Finance;

use App\Support\Finance\FinanceActionPermission;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\TestCase;

final class FinanceActionPermissionMatrixTest extends TestCase
{
    private function resolve(string $routeName, array $inputs = [], string $uri = '/admin/test'): ?array
    {
        $request = Request::create($uri, 'POST', $inputs);
        $route = new Route('POST', 'admin/test', static fn () => null);
        $route->name($routeName);
        $request->setRouteResolver(static fn () => $route);
        return FinanceActionPermission::forRequest($request);
    }

    public function test_each_finance_module_has_independent_actions(): void
    {
        foreach (FinanceActionPermission::MODULES as $module) {
            foreach (FinanceActionPermission::ACTIONS as $action) {
                self::assertSame('finance '.$module.' '.$action,
                    FinanceActionPermission::name($module, $action));
            }
        }
        self::assertNotSame(FinanceActionPermission::name('cr', 'delete'),
            FinanceActionPermission::name('cr', 'reverse'));
        self::assertNotSame(FinanceActionPermission::name('cheque', 'clear'),
            FinanceActionPermission::name('cheque', 'reject'));
        self::assertNotSame(FinanceActionPermission::name('jv', 'create'),
            FinanceActionPermission::name('cv', 'create'));
    }

    public function test_invalid_permissions_cannot_generate_unknown_abilities(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FinanceActionPermission::name('unknown-module', 'delete');
    }

    public function test_route_actions_are_distinct(): void
    {
        self::assertSame(['cheque','create'],$this->resolve('finance.cheques.store'));
        self::assertSame(['cheque','clear'],$this->resolve('finance.cheques.action',['action'=>'clearing']));
        self::assertSame(['cheque','withdraw'],$this->resolve('finance.cheques.action',['action'=>'withdrawal']));
        self::assertSame(['cheque','return'],$this->resolve('finance.cheques.finalize',['decision'=>'returned']));
        self::assertSame(['cheque','reject'],$this->resolve('finance.cheques.finalize',['decision'=>'rejected']));
        self::assertSame(['online','confirm'],$this->resolve('finance.online.confirm'));
        self::assertSame(['cv','approve'],$this->resolve('commision.voucher.approve'));
        self::assertSame(['plot-receipt','create'],$this->resolve('finance.voucher.plot-receipt.post'));
        self::assertSame(['plot-purchase','reverse'],$this->resolve('finance.voucher.plot-purchase.reverse'));
        self::assertSame(['jv','create'],$this->resolve('journal.voucher.store'));
    }

    public function test_daily_directory_view_is_scoped_to_series(): void
    {
        self::assertSame(['ccr','view'],$this->resolve('finance.daily-vouchers.data',['type'=>'CCR']));
        self::assertSame(['plot-receipt','view'],$this->resolve('finance.daily-vouchers.data',['type'=>'PR']));
        self::assertSame(['directory','view'],$this->resolve('finance.daily-vouchers.data'));
    }

    public function test_granular_guard_is_mounted_and_permissions_migration_is_idempotent(): void
    {
        $root = dirname(__DIR__, 3);
        $kernel = file_get_contents($root.'/app/Http/Kernel.php');
        $routes = file_get_contents($root.'/routes/web.php');
        $migration = file_get_contents($root.'/database/migrations/2026_11_18_130000_register_finance_action_permissions.php');
        self::assertStringContainsString("'finance.action' =>", $kernel);
        self::assertStringContainsString("'check.user.status', 'finance.action'", $routes);
        self::assertStringContainsString('insertOrIgnore', $migration);
        self::assertStringContainsString('role_has_permissions', $migration);
        self::assertStringContainsString('model_has_permissions', $migration);
        self::assertStringContainsString('Never delete granted permission definitions', $migration);
        self::assertStringNotContainsString("->middleware(['permission:read voucher'])->name('finance.voucher.plot-receipt.post')", $routes);
    }

    public function test_voucher_listings_use_action_specific_controls(): void
    {
        $root = dirname(__DIR__, 3);
        $daily = file_get_contents($root.'/resources/views/admin/finance/voucher/daily_directory.blade.php');
        $receipt = file_get_contents($root.'/resources/views/admin/finance/voucher/plot_receipt_workspace.blade.php');
        $purchase = file_get_contents($root.'/resources/views/admin/finance/voucher/plot_purchase_workspace.blade.php');
        self::assertStringContainsString('FinanceActionPermission::moduleForSeries', $daily);
        self::assertStringContainsString('finance plot-receipt create', $receipt);
        self::assertStringContainsString('finance plot-receipt reverse', $receipt);
        self::assertStringContainsString('finance plot-purchase create', $purchase);
        self::assertStringContainsString('finance plot-purchase reverse', $purchase);
    }
}
