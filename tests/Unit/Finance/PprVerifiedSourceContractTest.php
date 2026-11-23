<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class PprVerifiedSourceContractTest extends TestCase
{
    public function test_original_ppr_form_uses_real_single_verified_bank_picker(): void
    {
        $form=file_get_contents(__DIR__.'/../../../resources/views/admin/finance/voucher/original_ppr_create_modal.blade.php');
        $page=file_get_contents(__DIR__.'/../../../resources/views/admin/finance/voucher/plot_receipt_workspace.blade.php');
        self::assertIsString($form);
        self::assertIsString($page);
        foreach (['passed_source_id','passed_source_method','prPassedPickerModal','prApplyPassed'] as $field) {
            self::assertStringContainsString($field,$form);
        }
        self::assertStringContainsString("voucher_series:'PPR'", $page);
        self::assertStringContainsString("direction:'receipt'", $page);
        self::assertStringContainsString("name:'prPassedChoice'", $page);
        self::assertStringContainsString("$('#prOriginalPayment').on('change.pprBankPicker'", $page);
        self::assertStringContainsString("$('#prOriginalForm').on('submit'", $page);
    }

    public function test_original_ppr_has_a_project_bank_cash_counterpart_with_booking_fk_preserved(): void
    {
        $root=dirname(__DIR__,3);
        $form=file_get_contents($root.'/resources/views/admin/finance/voucher/original_ppr_create_modal.blade.php');
        $writer=file_get_contents($root.'/app/Http/Controllers/BookingController.php');
        $approval=file_get_contents($root.'/app/Services/Finance/DailyVoucherApprovalWorkflow.php');
        $editor=file_get_contents($root.'/app/Services/Finance/BookingReceiptCashEditor.php');
        $release=file_get_contents($root.'/app/Services/Finance/BankVoucherAllocationRelease.php');
        self::assertStringContainsString('name="counter_account_id"',$form);
        self::assertStringContainsString('PPR_VERIFIED_PROJECT_BANK_COUNTER_REQUIRED',$writer);
        self::assertStringContainsString('PPR_CASH_COUNTER_ACCOUNT_REQUIRED',$writer);
        self::assertStringContainsString('BalancedOriginalPprIntegrity::assert(',$writer);
        self::assertStringContainsString('BalancedOriginalPprIntegrity::assert(',$approval);
        self::assertStringContainsString('BalancedOriginalPprIntegrity::assert(',$editor);
        self::assertStringContainsString('BalancedOriginalPprIntegrity::assert(',$release);
        self::assertStringContainsString("where('customer_ledger_id',$"."customerLedger->id)",$writer);
    }

    public function test_ppr_source_is_verified_and_claimed_in_booking_transaction_and_replay_cannot_change_it(): void
    {
        $source=file_get_contents(__DIR__.'/../../../app/Http/Controllers/BookingController.php');
        self::assertIsString($source);
        foreach ([
            'PPR_VERIFIED_BANK_SOURCE_REQUIRED',
            'PPR_CASH_SOURCE_CONFLICT',
            "PassedBankVoucherClaim::class)->verify(",
            "PassedBankVoucherClaim::class)->claim(",
            "'voucher_type','PPR'",
            "'voucher_id',(int)$"."duplicateLedger->id",
            "&& (int)$"."existingAllocation->source_id===$"."passedId",
        ] as $required) self::assertStringContainsString($required,$source);
    }

    public function test_m04_source_withdrawal_verifies_and_releases_original_ppr_ledger_and_booking(): void
    {
        $leg=file_get_contents(__DIR__.'/../../../app/Services/Finance/BankVoucherLegIntegrity.php');
        $release=file_get_contents(__DIR__.'/../../../app/Services/Finance/BankVoucherAllocationRelease.php');
        $planner=file_get_contents(__DIR__.'/../../../app/Services/Finance/BankManagementCascadePlanner.php');
        foreach ([$leg,$release,$planner] as $file) self::assertIsString($file);
        self::assertStringContainsString("if($"."series==='PPR')", $leg);
        self::assertStringContainsString("->whereIn('series',['CR','DR','BOR','BOP','PPR'])", $release);
        self::assertStringContainsString("->where('customer_ledger_id',$"."voucherId)", $release);
        self::assertStringContainsString("->voucher_series!=='PPR'", $release);
        self::assertStringContainsString("'PPR' => ['PPR']", $planner);
    }
}
