<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\CommissionVoucherController;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * A modern AJAX form must not accidentally remove original finance posting
 * safeguards. No database writes or fixtures are required by this test.
 */
class CommissionVoucherAjaxWriteGateTest extends TestCase
{
    public function test_ajax_commission_create_fails_closed_without_approved_isolated_pilot(): void
    {
        config()->set('finance.v2_pilot_api_enabled', false);
        config()->set('finance.v2_pilot_write_enabled', false);
        $request = Request::create('/commision-voucher/create?embed=1', 'POST', [
            'voucher_number' => 'CV-0000001',
        ]);
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        try {
            app(CommissionVoucherController::class)->store($request);
            $this->fail('Unapproved commission creation must never proceed.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }
}
