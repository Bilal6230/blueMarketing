<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\CommissionVoucherController;
use App\Http\Controllers\JournalVoucherController;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

/**
 * Phase 1K temporary fail-closed control. Explicitly tests both write entrypoints.
 * Does not interact with a database and does not assert the reversal design exists.
 */
class UnsafeVoucherActionGateTest extends TestCase
{
    public function test_journal_voucher_update_is_blocked_before_financial_mutation(): void
    {
        $this->assertBlocked(fn () => app(JournalVoucherController::class)
            ->update(Request::create('/test-jv/41', 'PUT'), 41));
    }

    public function test_journal_voucher_delete_is_blocked_before_financial_mutation(): void
    {
        $this->assertBlocked(fn () => app(JournalVoucherController::class)->destroy(41));
    }

    public function test_commission_voucher_create_is_blocked_before_financial_mutation(): void
    {
        $this->assertBlocked(fn () => app(CommissionVoucherController::class)
            ->store(Request::create('/test-cv', 'POST')));
    }

    public function test_commission_voucher_update_is_blocked_before_financial_mutation(): void
    {
        $this->assertBlocked(fn () => app(CommissionVoucherController::class)
            ->update(Request::create('/test-cv/41', 'PUT'), 41));
    }

    public function test_commission_voucher_delete_is_blocked_before_financial_mutation(): void
    {
        $this->assertBlocked(fn () => app(CommissionVoucherController::class)->destroy(41));
    }

    public function test_original_voucher_actions_remain_fail_closed_when_only_api_flag_is_on(): void
    {
        config()->set('finance.v2_pilot_api_enabled', true);
        config()->set('finance.v2_pilot_write_enabled', false);
        $this->assertBlocked(fn () => app(JournalVoucherController::class)->destroy(41));
        $this->assertBlocked(fn () => app(CommissionVoucherController::class)->destroy(41));
        $this->assertBlocked(fn () => app(CommissionVoucherController::class)
            ->update(Request::create('/test-cv/41', 'PUT'), 41));
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
