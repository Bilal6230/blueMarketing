<?php

namespace Tests\Feature\Finance;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Exercises registered HTTP route -> controller gates, with middleware bypassed.
 * Permission integration is checked separately from the routes' declarations.
 */
class VoucherSafetyHttpRouteTest extends TestCase
{
    public function test_real_voucher_write_routes_are_blocked_with_http_409(): void
    {
        $routes = [
            ['POST', 'commision.voucher.store', []],
            ['PUT', 'commision.voucher.update', ['id' => 41]],
            ['DELETE', 'commision.voucher.delete', ['id' => 41]],
            ['PUT', 'journal.voucher.update', ['id' => 41]],
            ['DELETE', 'journal.voucher.delete', ['id' => 41]],
        ];

        // Isolate controller behavior at the real registered URL. No database needed.
        $this->withoutMiddleware();

        foreach ($routes as [$method, $name, $params]) {
            $url = route($name, $params);
            $this->call($method, $url)->assertStatus(409);
        }
    }

    public function test_write_routes_keep_their_permission_middleware(): void
    {
        $expected = [
            'commision.voucher.store' => 'permission:create jv',
            'commision.voucher.update' => 'permission:edit jv',
            'commision.voucher.delete' => 'permission:delete jv',
            'journal.voucher.update' => 'permission:edit jv',
            'journal.voucher.delete' => 'permission:delete jv',
            'journal.voucher.store' => 'permission:create jv',
        ];

        foreach ($expected as $name => $permission) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing registered route: {$name}");
            $this->assertContains($permission, $route->gatherMiddleware());
        }
    }
}
