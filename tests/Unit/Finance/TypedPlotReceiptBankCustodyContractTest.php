<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class TypedPlotReceiptBankCustodyContractTest extends TestCase
{
    public function test_multi_plot_ppr_uses_actual_management_claim_not_customer_ledger_placeholder(): void
    {
        $writer=file_get_contents(__DIR__.'/../../../app/Services/Finance/PlotEntryWriter.php');
        $controller=file_get_contents(__DIR__.'/../../../app/Http/Controllers/Finance/VoucherController.php');
        $custody=file_get_contents(__DIR__.'/../../../app/Services/Finance/TypedPlotReceiptBankCustody.php');
        foreach([$writer,$controller,$custody] as $file)self::assertIsString($file);
        self::assertStringContainsString('TypedPlotReceiptBankCustody::class)->claimPosted(', $writer);
        self::assertStringContainsString("VerifiedBankInstrument::class)->inspectLocked(", $writer);
        self::assertStringContainsString('PPR_TYPED_BANK_DEBIT_ACCOUNT_REQUIRED', $writer);
        self::assertStringContainsString("'passed_source_method' => ['nullable','in:online,cheque']", $controller);
        self::assertStringContainsString("'passed_source_id' => ['nullable','integer','min:1']", $controller);
        self::assertStringContainsString("public const POSTED='PPR_TYPED'", $custody);
        self::assertStringContainsString("public const DRAFT='PPR_TYPED_DRAFT'", $custody);
        self::assertStringContainsString("'voucher_type'=>$"."type,'voucher_id'=>$"."voucherId", $custody);
    }

    public function test_approval_and_m04_source_reissue_release_typed_ppr_without_legacy_mutation(): void
    {
        $draft=file_get_contents(__DIR__.'/../../../app/Services/Finance/PlotVoucherApprovalDraftWriter.php');
        $approval=file_get_contents(__DIR__.'/../../../app/Services/Finance/DailyVoucherApprovalWorkflow.php');
        $replace=file_get_contents(__DIR__.'/../../../app/Services/Finance/BankManagementSourceMutation.php');
        $planner=file_get_contents(__DIR__.'/../../../app/Services/Finance/BankManagementCascadePlanner.php');
        $editor=file_get_contents(__DIR__.'/../../../app/Services/Finance/PlotVoucherEditor.php');
        $life=file_get_contents(__DIR__.'/../../../app/Services/Finance/PlotEntryLifecycleService.php');
        foreach([$draft,$approval,$replace,$planner,$editor,$life] as $file)self::assertIsString($file);
        self::assertStringContainsString('TypedPlotReceiptBankCustody::class)->reserveDraft(', $draft);
        self::assertStringContainsString('TypedPlotReceiptBankCustody::class)->release(', $draft);
        self::assertStringContainsString('$"."actorId);", $approval);
        self::assertStringContainsString('TypedPlotReceiptBankCustody::class)->release(', $replace);
        self::assertStringContainsString('TypedPlotReceiptBankCustody::POSTED', $planner);
        self::assertStringContainsString('TypedPlotReceiptBankCustody::POSTED', $editor);
        self::assertStringContainsString('TypedPlotReceiptBankCustody::POSTED', $life);
    }

    public function test_typed_ppr_has_same_verified_source_picker_and_narration_without_regressing_original_ppr(): void
    {
        $view=file_get_contents(__DIR__.'/../../../resources/views/admin/finance/voucher/plot_receipt_workspace.blade.php');
        self::assertIsString($view);
        foreach(['prTypedPayment','prTypedPassedId','prTypedPassedMethod',
            'prTypedOpenPassedPicker','prPickerTarget','prTypedRemaining',
            'prPassedPickerModal','prOriginalPayment','prOriginalForm',
            'prApplyPassed','$.financeVerifiedSourceNarration.set('] as $key){
            self::assertStringContainsString($key,$view);
        }
        self::assertStringContainsString("voucher_series:'PPR'", $view);
    }
}
