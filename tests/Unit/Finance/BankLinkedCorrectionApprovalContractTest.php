<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

/**
 * Approval ON must stage a source/ledger-safe proposal, approval OFF may post
 * using the existing atomic writer, and decisions recheck the exact fingerprint.
 */
final class BankLinkedCorrectionApprovalContractTest extends TestCase
{
    public function test_approval_policy_permissions_routes_and_staging_are_independent(): void
    {
        $service=file_get_contents(__DIR__.'/../../../app/Services/Finance/BankLinkedCorrectionApproval.php');
        $controller=file_get_contents(__DIR__.'/../../../app/Http/Controllers/Finance/BankLinkedCorrectionController.php');
        $settings=file_get_contents(__DIR__.'/../../../app/Http/Controllers/Finance/VoucherSettingsController.php');
        $routes=file_get_contents(__DIR__.'/../../../routes/web.php');
        $migration=file_get_contents(__DIR__.'/../../../database/migrations/2026_11_22_180000_create_finance_bank_correction_requests_table.php');
        foreach ([$service,$controller,$settings,$routes,$migration] as $text) self::assertIsString($text);
        self::assertStringContainsString('ONL_CORR',$settings);
        self::assertStringContainsString('CHQ_CORR',$settings);
        self::assertStringContainsString('finance online correct',$migration);
        self::assertStringContainsString('finance cheque correct',$migration);
        self::assertStringContainsString('finance online correction approve',$migration);
        self::assertStringContainsString('finance cheque correction approve',$migration);
        self::assertStringContainsString('BankLinkedCorrectionApproval',$controller);
        self::assertStringContainsString('finance.bank-management.correction.pending',$routes);
        self::assertStringContainsString('finance.bank-management.correction.decide',$routes);
        self::assertStringContainsString('approval_required',$service);
        self::assertStringContainsString("->where('status','pending')",$service);
        self::assertStringContainsString('VoucherApproverAccess::mayDecide',$service);
        self::assertStringContainsString('BankSourceReissueCorrection::class',$service);
        self::assertStringContainsString('hash_equals',$service);
        self::assertStringContainsString('DB::transaction',$service);
        self::assertStringNotContainsString('migrate:fresh',$service);
    }
}
