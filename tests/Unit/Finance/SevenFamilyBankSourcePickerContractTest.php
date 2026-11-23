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

    public function test_bd_bw_and_typed_multi_plot_ppr_validate_verified_bank_sources(): void
    {
        $controller=file_get_contents(__DIR__.'/../../../app/Http/Controllers/Finance/VoucherController.php');
        self::assertIsString($controller);
        $bdStart=strpos($controller,'public function postTransferVoucher(');
        self::assertNotFalse($bdStart);
        $transfer=substr($controller,$bdStart,3800);
        self::assertStringContainsString("'passed_source_method' => ['nullable','in:online,cheque']", $transfer);
        self::assertStringContainsString("'passed_source_id' => ['nullable','integer','min:1']", $transfer);
        self::assertStringContainsString('$passedMethod,$passedId', $transfer);
        $pprStart=strpos($controller,'public function postPlotReceipt(');
        self::assertNotFalse($pprStart);
        $plot=substr($controller,$pprStart,2100);
        self::assertStringContainsString("'passed_source_method' => ['nullable','in:online,cheque']", $plot);
        self::assertStringContainsString("'passed_source_id' => ['nullable','integer','min:1']", $plot);
        self::assertStringContainsString("'payment_type' => ['nullable','integer','in:1,2,3']", $plot);
        self::assertStringContainsString('Select a verified Cheque or Online source matching Payment Type', $plot);
    }
}
