<?php

namespace Tests\Feature\Compatibility;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Laravel 13 route contract checks. No request, writes or production DB. */
final class Laravel13RouteContractTest extends TestCase
{
    public function test_core_and_mobile_modules_keep_critical_named_routes(): void
    {
        $expected = [
            'dashboard' => 'GET',
            'crm.lead.index' => 'GET',
            'project.index' => 'GET',
            'booking.plot.index' => 'GET',
            'labours.index' => 'GET',
            'journal.voucher.index' => 'GET',
            'commision.voucher.index' => 'GET',
            'stocks.index' => 'GET',
            'landing-page.admin.index' => 'GET',
        ];
        foreach ($expected as $name=>$method) {
            $route=Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing Laravel 13 route: {$name}");
            $this->assertContains($method, $route->methods(), $name);
        }
    }

    public function test_mobile_api_and_finance_post_routes_keep_expected_methods(): void
    {
        $expected = [
            'api/v1/mobile/auth/login'=>'POST',
            'api/v1/mobile/projects'=>'GET',
            'api/v1/mobile/crm/leads'=>'GET',
            'api/v1/mobile/attendance/staff/today'=>'GET',
            'api/v1/mobile/stock/items'=>'GET',
            'admin/journal-voucher/create'=>'POST',
            'admin/commision-voucher/create'=>'POST',
        ];
        foreach ($expected as $uri=>$method) {
            $found=false;
            // Iterate the RouteCollection's public Route objects; internal
            // method-index maps are nested and are not a flat route list.
            foreach (Route::getRoutes()->getRoutes() as $route) {
                if ($route->uri()===$uri && in_array($method,$route->methods(),true)) {
                    $found=true;
                    break;
                }
            }
            $this->assertTrue($found, "Missing {$method} {$uri}");
        }
    }

    public function test_known_financial_write_permissions_still_attached(): void
    {
        foreach (['journal.voucher.store','commision.voucher.store'] as $name) {
            $route=Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertContains('permission:create jv',$route->gatherMiddleware());
        }
    }
}
