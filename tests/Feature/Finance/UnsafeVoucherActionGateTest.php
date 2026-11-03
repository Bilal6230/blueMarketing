<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\CommissionVoucherController;
use App\Http\Controllers\JournalVoucherController;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

/**
 * Temporary fail-closed controls for actions which still lack a safe legacy
 * creation/reversal workflow. JV/CV corrections have separate database tests.
 */
class UnsafeVoucherActionGateTest extends TestCase
{
    public function test_commission_voucher_create_is_blocked_before_financial_mutation(): void
    {
        $this->assertBlocked(fn () => app(CommissionVoucherController::class)
            ->store(Request::create('/test-cv', 'POST')));
    }

    public function test_original_voucher_actions_remain_fail_closed_when_only_api_flag_is_on(): void
    {
        config()->set('finance.v2_pilot_api_enabled', true);
        config()->set('finance.v2_pilot_write_enabled', false);
        $this->assertBlocked(fn () => app(CommissionVoucherController::class)
            ->store(Request::create('/test-cv', 'POST')));
    }

    private function assertBlocked(callable $action): void
    {
        try {
            $action();
            $this->fail('Unsafe voucher action was not blocked.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertStringContainsString('accounting history', $exception->getMessage());
        }
    }
}
