<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

/** Controller, routes and front-end contract; integration ledger tests run separately. */
final class BankLinkedCorrectionHttpContractTest extends TestCase
{
    public function test_review_and_confirm_are_independent_permissioned_endpoints(): void
    {
        $routes=file_get_contents(__DIR__.'/../../../routes/web.php');
        $controller=file_get_contents(__DIR__.'/../../../app/Http/Controllers/Finance/BankLinkedCorrectionController.php');
        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertStringContainsString('finance.bank-management.correction.preview',$routes);
        self::assertStringContainsString('finance.bank-management.correction.confirm',$routes);
        self::assertStringContainsString('BankLinkedCorrectionController::class',$routes);
        self::assertStringContainsString('finance ', $controller);
        self::assertStringContainsString("user->can('delete voucher')",$controller);
        self::assertStringContainsString('VoucherApproverAccess::isSuperAdmin($user)',$controller);
        self::assertStringContainsString('function preview(',$controller);
        self::assertStringContainsString('function confirm(',$controller);
        self::assertStringContainsString("'fingerprint'",$controller);
        self::assertStringContainsString("'vouchers.*'",$controller);
    }

    public function test_cheque_and_online_have_separate_jquery_review_wizard(): void
    {
        $script=file_get_contents(__DIR__.'/../../../resources/views/admin/finance/voucher/bank_linked_correction_script.blade.php');
        $cheque=file_get_contents(__DIR__.'/../../../resources/views/admin/finance/cheque/intake.blade.php');
        $online=file_get_contents(__DIR__.'/../../../resources/views/admin/finance/online/intake.blade.php');
        foreach ([$script,$cheque,$online] as $source) self::assertIsString($source);
        self::assertStringContainsString('bank-linked-correction-ready',$script);
        self::assertStringContainsString('bank-linked-correction-ready',$cheque);
        self::assertStringContainsString('bank-linked-correction-ready',$online);
        self::assertStringContainsString('Confirm Atomic Correction',$script);
        self::assertStringContainsString('vouchers[id]=val',$script);
        self::assertStringContainsString('fingerprint:plan.fingerprint',$script);
        self::assertStringContainsString("modal('hide')",$script);
        self::assertStringContainsString("bank_linked_correction_script",$online);
        self::assertStringContainsString("bank_linked_correction_script",$cheque);
        self::assertStringNotContainsString('location.reload(', $script);
    }
}
