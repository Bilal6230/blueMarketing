<?php

namespace Tests\Feature\Finance;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Real voucher routes: guest access cannot reach finance actions.
 *
 * These tests intentionally keep Laravel authentication middleware ENABLED.
 * No application database schema, role seeders or permissions are needed.
 */
class VoucherGuestAccessIntegrationTest extends TestCase
{
    private const VOUCHER_ROUTES = [
        ['GET', 'journal.voucher.index', []],
        ['GET', 'journal.voucher.create', []],
        ['POST', 'journal.voucher.store', []],
        ['GET', 'journal.voucher.edit', ['id' => 41]],
        ['PUT', 'journal.voucher.update', ['id' => 41]],
        ['DELETE', 'journal.voucher.delete', ['id' => 41]],
        ['GET', 'journal.voucher.print', ['id' => 41]],
        ['GET', 'commision.voucher.index', []],
        ['GET', 'commision.voucher.create', []],
        ['POST', 'commision.voucher.store', []],
        ['GET', 'commision.voucher.edit', ['id' => 41]],
        ['PUT', 'commision.voucher.update', ['id' => 41]],
        ['DELETE', 'commision.voucher.delete', ['id' => 41]],
        ['GET', 'commision.voucher.print', ['id' => 41]],
    ];

    public function test_all_fourteen_finance_voucher_routes_have_auth_and_action_permissions(): void
    {
        $permissions = [
            'index' => 'read jv',
            'create' => 'create jv',
            'store' => 'create jv',
            'edit' => 'edit jv',
            'update' => 'edit jv',
            'delete' => 'delete jv',
            'print' => 'print jv',
        ];
        foreach (self::VOUCHER_ROUTES as [$method, $name, $parameters]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing route {$name}");
            $this->assertContains($method, $route->methods(), "{$name} method mismatch");
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth', $middleware, "{$name} missing auth");
            $action = substr($name, strrpos($name, '.') + 1);
            $this->assertContains('permission:' . $permissions[$action], $middleware, "{$name} missing permission");
        }
    }

    public function test_guest_cannot_reach_read_create_edit_print_or_write_actions(): void
    {
        foreach (self::VOUCHER_ROUTES as [$method, $name, $parameters]) {
            $response = $this->call($method, route($name, $parameters));
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403],
                "{$name} returned {$response->getStatusCode()} for unauthenticated request"
            );
            if ($response->getStatusCode() === 302) {
                $this->assertStringContainsString('/login', $response->headers->get('Location', ''));
            }
        }
    }

    public function test_write_routes_remain_blocked_after_authentication_is_explicitly_bypassed(): void
    {
        $this->withoutMiddleware();
        foreach (self::VOUCHER_ROUTES as [$method, $name, $parameters]) {
            if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
                continue;
            }
            if ($name === 'journal.voucher.store') {
                // JV creation is still active; it is not part of the temporary 409 gate.
                continue;
            }
            $this->call($method, route($name, $parameters))->assertStatus(409);
        }
    }
}
