<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class BankSourceReissueContractTest extends TestCase
{
    public function test_source_reissue_uses_locked_atomic_soft_void_not_physical_deletion(): void
    {
        $service=file_get_contents(__DIR__.'/../../../app/Services/Finance/BankSourceReissueCorrection.php');
        $approval=file_get_contents(__DIR__.'/../../../app/Services/Finance/BankLinkedCorrectionApproval.php');
        $controller=file_get_contents(__DIR__.'/../../../app/Http/Controllers/Finance/BankLinkedCorrectionController.php');
        self::assertIsString($service);
        self::assertIsString($approval);
        self::assertIsString($controller);
        self::assertStringContainsString('lockForUpdate()',$service);
        self::assertStringContainsString('DB::transaction(',$service);
        self::assertStringContainsString('BankManagementSourceMutation::class',$service);
        self::assertStringContainsString('->replace(',$service);
        self::assertStringContainsString('->update(',$service);
        self::assertStringContainsString('bank_source_fix',$service);
        self::assertStringContainsString('BankVoucherLegIntegrity::assert',$service);
        self::assertStringContainsString('VoucherApprovalMutationGuard::class',$service);
        self::assertStringContainsString('ready_for_new_vouchers',$service);
        self::assertStringContainsString('ready_after_correction',$service);
        self::assertStringContainsString('requires_replacement',$service);
        self::assertStringNotContainsString('->delete(',$service);
        self::assertStringContainsString('submitReissue(',$approval);
        self::assertStringContainsString("mode'=>'reissue'",$approval);
        self::assertStringContainsString('BankSourceReissueCorrection::class',$approval);
        self::assertStringContainsString('BankSourceReissueCorrection $correction',$controller);
        self::assertStringNotContainsString('BankLinkedCorrection::class',$controller);
    }

    public function test_verified_picker_autofills_details_and_checks_correction_evidence(): void
    {
        $picker=file_get_contents(__DIR__.'/../../../app/Http/Controllers/Finance/PassedBankSourcePickerController.php');
        $claim=file_get_contents(__DIR__.'/../../../app/Services/Finance/PassedBankVoucherClaim.php');
        $view=file_get_contents(__DIR__.'/../../../resources/views/admin/finance/voucher/cash_voucher.blade.php');
        $evidence=file_get_contents(__DIR__.'/../../../app/Services/Finance/BankSourceCorrectionEvidence.php');
        foreach([$picker,$claim,$view,$evidence] as $source) self::assertIsString($source);
        self::assertStringContainsString('BankSourceCorrectionEvidence::matchesOnlineAmount',$picker);
        self::assertStringContainsString('BankSourceCorrectionEvidence::matchesOnlineAmount',$claim);
        self::assertStringContainsString("'cheque_title'",$picker);
        self::assertStringContainsString("'cheque_last4'",$picker);
        self::assertStringContainsString("'sending_bank'",$picker);
        self::assertStringContainsString("'receiving_bank'",$picker);
        self::assertStringContainsString('function setPassedBankNarration(source)',$view);
        self::assertStringContainsString('Transaction ID / Reference: ',$view);
        self::assertStringContainsString('Cheque Number: ****',$view);
        self::assertStringContainsString('Clearing Date: ',$view);
        self::assertStringContainsString('[BANK SOURCE]',$view); // legacy block cleanup
        self::assertStringContainsString('SOURCE Cheque',$view);
        self::assertStringContainsString('SOURCE Online',$view);
        self::assertStringContainsString("parts.join(' | ')",$view);
        self::assertStringContainsString('Cheque Number: ****',$view);
        self::assertStringContainsString('bank_source_fix',$evidence);
        self::assertStringNotContainsString('forceDelete(', $evidence);
    }
}
