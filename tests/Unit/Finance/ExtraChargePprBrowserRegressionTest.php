<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class ExtraChargePprBrowserRegressionTest extends TestCase
{
    public function test_extra_charge_duplicate_slip_reports_actionable_message_without_exposing_sql(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/BookingController.php'));
        self::assertStringContainsString("'EXTRA_CHARGE_SOURCE_ALREADY_POSTED', 'FINANCE_SOURCE_DUPLICATE'", $controller);
        self::assertStringContainsString('Enter a different Slip No.', $controller);
        self::assertStringContainsString("return response()->json(['message' => $safeMessage], 422)", $controller);
    }

    public function test_plot_receipt_register_displays_new_and_approvals_actions(): void
    {
        $view = file_get_contents(base_path('resources/views/admin/finance/voucher/plot_receipt_workspace.blade.php'));
        self::assertStringContainsString("finance.daily-vouchers.approvals.index", $view);
        self::assertStringContainsString('id="prNewVoucher"', $view);
        self::assertStringContainsString('data-target="#prOriginalCreateModal"', $view);
        self::assertStringContainsString("auth()->user()?->can('add slip')", $view);
        self::assertSame(1, substr_count($view, 'id="prNewVoucher"'));
    }

    public function test_original_ppr_action_accepts_existing_slip_permission(): void
    {
        $access = file_get_contents(base_path('app/Http/Middleware/AuthorizeBookingDeposit.php'));
        self::assertStringContainsString("can('finance plot-receipt create')", $access);
        self::assertStringContainsString("can('add slip')", $access);
    }
}
