<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class SevenFamilyBankSourcePickerContractTest extends TestCase
{
    public function test_seven_voucher_families_share_direction_safe_verified_source_picker(): void
    {
        $controller=file_get_contents(__DIR__.'/../../../app/Http/Controllers/Finance/PassedBankSourcePickerController.php');
        $view=file_get_contents(__DIR__.'/../../../resources/views/admin/finance/voucher/cash_voucher.blade.php');
        self::assertIsString($controller);
        self::assertIsString($view);
        foreach (['CR','DR','BOR','BOP','BD','BW','PPR'] as $series) {
            self::assertStringContainsString("'".$series."'", $controller);
        }
        self::assertStringContainsString("'voucher_series'=>['nullable',Rule::in(", $controller);
        self::assertStringContainsString("abort(422,'Selected voucher series and bank source direction do not match.')", $controller);
        self::assertStringContainsString('voucher_series:@json($displaySeries ?? $voucherType)', $view);
        self::assertStringContainsString("'finance_bank_voucher_allocation_releases'", $controller);
        self::assertStringContainsString("'finance_online_confirmation_events'", $controller);
        self::assertStringContainsString("'finance_cheque_intake_actions'", $controller);
    }

    public function test_typed_vouchers_cannot_silently_ignore_unimplemented_verified_source_claim(): void
    {
        $controller=file_get_contents(__DIR__.'/../../../app/Http/Controllers/Finance/VoucherController.php');
        self::assertIsString($controller);
        foreach (['postTransferVoucher','postPlotReceipt'] as $method) {
            $start=strpos($controller,'public function '.$method.'(');
            self::assertNotFalse($start);
            $section=substr($controller,$start,1000);
            self::assertStringContainsString("!$"."request->filled('passed_source_id')", $section);
            self::assertStringContainsString("!$"."request->filled('passed_source_method')", $section);
            self::assertStringContainsString('typed allocation workflow', $section);
        }
    }
}
