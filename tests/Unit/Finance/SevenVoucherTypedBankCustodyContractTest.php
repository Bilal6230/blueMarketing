<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class SevenVoucherTypedBankCustodyContractTest extends TestCase
{
    public function test_bd_bw_workflow_cannot_treat_verified_intake_as_old_customer_cheque(): void
    {
        $writer=file_get_contents(__DIR__.'/../../../app/Services/Finance/BankTransferWriter.php');
        $draft=file_get_contents(__DIR__.'/../../../app/Services/Finance/BankTransferApprovalDraftWriter.php');
        $controller=file_get_contents(__DIR__.'/../../../app/Http/Controllers/Finance/VoucherController.php');
        $migration=file_get_contents(__DIR__.'/../../../database/migrations/2026_11_23_150000_add_verified_bank_source_to_transfer_approval_drafts.php');
        foreach([$writer,$draft,$controller,$migration] as $file)self::assertIsString($file);
        self::assertStringContainsString('TypedBankVoucherCustody::class)->claimPosted(', $writer);
        self::assertStringContainsString('TypedBankVoucherCustody::class)->promoteDraft(', $writer);
        self::assertStringContainsString('TypedBankVoucherCustody::class)->reserveDraft(', $draft);
        self::assertStringContainsString("'passed_source_method' => ['nullable','in:online,cheque']", $controller);
        self::assertStringContainsString("'passed_source_id' => ['nullable','integer','min:1']", $controller);
        self::assertStringContainsString('passed_source_method', $migration);
        self::assertStringContainsString('passed_source_id', $migration);
        self::assertStringNotContainsString("->value('linked_customer_ledger_id')", $draft);
    }

    public function test_management_withdrawal_and_reversal_know_typed_source_identity(): void
    {
        $release=file_get_contents(__DIR__.'/../../../app/Services/Finance/TypedBankVoucherCustody.php');
        $withdraw=file_get_contents(__DIR__.'/../../../app/Services/Finance/BankManagementSourceMutation.php');
        $planner=file_get_contents(__DIR__.'/../../../app/Services/Finance/BankManagementCascadePlanner.php');
        $editor=file_get_contents(__DIR__.'/../../../app/Services/Finance/BankTransferEditor.php');
        $reversal=file_get_contents(__DIR__.'/../../../app/Services/Finance/BankTransferLifecycleService.php');
        foreach([$release,$withdraw,$planner,$editor,$reversal] as $file)self::assertIsString($file);
        foreach(['BD_DRAFT','BW_DRAFT','approval_transfer','pending_cancel_release','posted_void_release',
            'finance_bank_voucher_allocation_releases','legacy_ledger_id'] as $needle){
            self::assertStringContainsString($needle,$release);
        }
        self::assertStringContainsString('TypedBankVoucherCustody::class)->release(', $withdraw);
        self::assertStringContainsString("'BD_DRAFT','BW_DRAFT'", $planner);
        self::assertStringContainsString('Verified bank-linked voucher requires audited source release', $editor);
        self::assertStringContainsString('BANK_TRANSFER_VERIFIED_SOURCE_REQUIRES_CUSTODY_RELEASE', $reversal);
    }

    public function test_bd_bw_and_original_ppr_picker_fields_match_shared_api(): void
    {
        $bank=file_get_contents(__DIR__.'/../../../resources/views/admin/finance/voucher/bank_transfer_workspace.blade.php');
        $ppr=file_get_contents(__DIR__.'/../../../resources/views/admin/finance/voucher/plot_receipt_workspace.blade.php');
        self::assertIsString($bank);
        self::assertIsString($ppr);
        foreach(['btPaymentType','btPassedPickerModal','btApplyPassed','passed_source_method:',
            'passed_source_id:','voucher_series:'] as $needle){
            self::assertStringContainsString($needle,$bank);
        }
        self::assertStringContainsString("voucher_series:'PPR'", $ppr);
    }
}
