<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class CashCreditDebitEditContractTest extends TestCase
{
    public function test_cash_credit_and_cash_debit_vouchers_reach_same_source_fk_safe_update(): void
    {
        $root=dirname(__DIR__,3);
        $controller=file_get_contents($root.'/app/Http/Controllers/LedgerController.php');
        $service=file_get_contents($root.'/app/Services/Finance/SameSourceCashVoucherEdit.php');
        self::assertIsString($controller);
        self::assertIsString($service);
        $start=strpos($controller,'public function update(Request $request, ?int $paymentType = null)');
        self::assertNotFalse($start);
        $update=substr($controller,$start,
            strpos($controller,'public function',$start+20)-$start);
        self::assertStringContainsString("whereIn('type', ['CR','CP','CCR','CDR'])",$update);
        self::assertStringContainsString('SameSourceCashVoucherEdit::class',$update);
        self::assertStringContainsString("whereIn('transaction_type',['CR','CP','CCR','CDR'])",$service);
        self::assertStringContainsString("['CR','CCR']",$service);
        self::assertStringContainsString("['CP','DR','CDR']",$service);
    }
}
