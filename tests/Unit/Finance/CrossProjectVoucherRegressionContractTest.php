<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class CrossProjectVoucherRegressionContractTest extends TestCase
{
    public function test_extra_charge_accepts_charge_now_without_payment_method(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/BookingController.php'));
        self::assertStringContainsString("=== 'extra_charge'", $controller);
        self::assertStringContainsString("'nullable|in:0,1' : 'required|in:1,2,3'", $controller);
    }

    public function test_selected_project_finance_access_still_checks_permissions_and_active_project(): void
    {
        $access = file_get_contents(base_path('app/Services/Finance/FinanceWorkflowAccess.php'));
        self::assertSame(2, substr_count($access, '(int) getSelectedTown() === $projectId'));
        self::assertStringContainsString('FinanceActionPermission::forRequest(request())', $access);
        self::assertSame(2, substr_count($access, 'where(\'id\', $projectId)->where(\'is_active\', 1)'));
    }

    public function test_modern_ppr_register_includes_original_booking_receipts_without_reposting(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/Finance/VoucherController.php'));
        $view = file_get_contents(base_path('resources/views/admin/finance/voucher/plot_receipt_workspace.blade.php'));
        self::assertStringContainsString("DB::table('booking_vouchers as bv')", $controller);
        self::assertStringContainsString("where('bv.voucher_series', 'PPR')", $controller);
        self::assertStringContainsString("'legacy'=>true", $controller);
        self::assertStringContainsString('if(r.legacy)', $view);
        self::assertStringContainsString("data-target=\"#prOriginalCreateModal\"", $view);
        self::assertStringContainsString("original_ppr_create_modal", $view);
        self::assertStringContainsString("$('#prOriginalForm').on('submit'", $view);
        $modernPpr = file_get_contents(base_path('resources/views/admin/finance/voucher/original_ppr_create_modal.blade.php'));
        self::assertStringContainsString("route('booking.customer.deposit')", $modernPpr);
        self::assertStringContainsString('name="payment_type"', $modernPpr);
        self::assertStringContainsString('name="cheque_purpose"', $modernPpr);
        self::assertStringContainsString("data-legacy-print-base", $view);
    }

    public function test_original_booking_ppr_uses_one_number_across_source_and_voucher(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/BookingController.php'));
        self::assertStringContainsString("\$newPprNumber = \$duplicateLedger ? null", $controller);
        self::assertStringContainsString("'type_id' => \$newPprNumber", $controller);
        self::assertStringContainsString("\$displayVoucherNumber = (int)\$newPprNumber", $controller);
    }
}
