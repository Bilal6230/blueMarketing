<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class VoucherApprovalPolicyRoutingRegressionTest extends TestCase
{
    public function test_normal_erp_cannot_skip_configured_voucher_policies(): void
    {
        $workflow = file_get_contents(base_path('app/Services/Finance/DailyVoucherApprovalWorkflow.php'));
        $policy = substr($workflow, strpos($workflow, 'public function policy('),
            strpos($workflow, 'public function decide(') - strpos($workflow, 'public function policy('));
        self::assertStringNotContainsString('normalErpMode()', $policy);
        self::assertStringContainsString('->where(\'voucher_type\', $type)', $policy);
        self::assertStringContainsString('->where(\'approval_required\', true)', $policy);
    }

    public function test_original_booking_ppr_source_and_ledger_stay_inactive_during_approval(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/BookingController.php'));
        self::assertStringContainsString('->submitIfRequired($projectId, \'PPR\', \'customer_ledger\'', $controller);
        self::assertStringContainsString('$customerLedger->forceFill([\'is_active\' => 0, \'is_approve\' => 0])->save()', $controller);
        self::assertStringContainsString('$ledger->forceFill([\'is_active\' => 0])->save()', $controller);
    }
}
