<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\CashVoucherLifecycleService;
use Tests\TestCase;

final class NormalTypedCashReversalGateTest extends TestCase
{
    public function test_typed_cash_reversal_is_forbidden_on_production_connection(): void
    {
        config(['app.env' => 'production']);
        $this->expectException(\RuntimeException::class);
        (new CashVoucherLifecycleService())->reverse(
            3, 12, str_repeat('t', 24), '2026-11-17', 'Local test', null
        );
    }

    public function test_source_fk_and_approval_gates_are_preserved(): void
    {
        $service = file_get_contents(app_path('Services/Finance/CashVoucherLifecycleService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Finance/DailyVoucherDirectoryController.php'));
        $routes = file_get_contents(base_path('routes/web.php'));
        $view = file_get_contents(resource_path('views/admin/finance/voucher/daily_directory.blade.php'));

        $this->assertStringContainsString("where('posting.customer_ledger_id', \$sourceId)", $service);
        $this->assertStringContainsString("where('claim.source_kind', 'cash')", $service);
        $this->assertStringContainsString('V2_CASH_REVERSE_BANK_CUSTODY_REQUIRED', $service);
        $this->assertStringContainsString('assertNoPendingCorrection(', $service);
        $this->assertStringContainsString('reversePostedBatch(', $service);
        $this->assertStringContainsString("'is_active' => 0", $service);
        $this->assertStringContainsString('reversal_of_batch_id', $controller);
        $this->assertStringContainsString('orWhereExists($reversedTypedExists)', $controller);
        $this->assertStringContainsString('function reverseTypedCashSource(', $controller);
        $this->assertStringContainsString("'status' => \$typedPosting && \$typedPosting->reversal_id", $controller);
        $this->assertStringContainsString("'reverse_url' => \$typedPosting", $controller);
        $this->assertStringContainsString("name('finance.daily-vouchers.sources.reverse')", $routes);
        $this->assertStringContainsString('dvTypedReverseModal', $view);
        $this->assertStringContainsString('dv-typed-reverse', $view);
        $this->assertStringNotContainsString("window.prompt('Reason for reversing '", $view);
    }
}
