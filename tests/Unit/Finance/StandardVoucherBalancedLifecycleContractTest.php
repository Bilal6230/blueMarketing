<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class StandardVoucherBalancedLifecycleContractTest extends TestCase
{
    public function test_all_standard_vouchers_offer_an_opposite_project_account(): void
    {
        $root=dirname(__DIR__,3);
        $view=file_get_contents($root.'/resources/views/admin/finance/voucher/cash_voucher.blade.php');
        $writer=file_get_contents($root.'/app/Http/Controllers/LedgerController.php');
        $cashOnly=file_get_contents($root.'/app/Http/Controllers/Finance/CashOnlyVoucherController.php');
        self::assertIsString($view);
        self::assertStringContainsString("['CR','DR','CP','CCR','CDR']", $view);
        self::assertStringContainsString('name="counter_account_id"', $view);
        self::assertStringContainsString("whereKey((int)$"."request->input('counter_account_id'))",$writer);
        self::assertStringContainsString('$standardCounter->id',$writer);
        self::assertStringContainsString("'counter_account_id'=>['required','integer','min:1']",$cashOnly);
        self::assertStringContainsString('$counterAccount->id',$cashOnly);
    }

    public function test_approval_release_and_same_source_edit_are_two_leg_aware(): void
    {
        $root=dirname(__DIR__,3);
        $approval=file_get_contents($root.'/app/Services/Finance/DailyVoucherApprovalWorkflow.php');
        $release=file_get_contents($root.'/app/Services/Finance/BankVoucherAllocationRelease.php');
        $planner=file_get_contents($root.'/app/Services/Finance/BankManagementCascadePlanner.php');
        $editor=file_get_contents($root.'/app/Services/Finance/SameSourceCashVoucherEdit.php');
        foreach([$approval,$release,$planner,$editor] as $file)self::assertIsString($file);
        self::assertStringContainsString('BalancedStandardVoucherIntegrity::assert(',$approval);
        self::assertStringContainsString('BalancedStandardVoucherIntegrity::assert(',$release);
        self::assertStringContainsString('BalancedStandardVoucherIntegrity::assert(',$planner);
        self::assertStringContainsString('counter_leg_id',$editor);
        self::assertStringContainsString("['CP','DR','CDR']",$editor);
        self::assertStringContainsString("['CR','DR','CP','CCR','CDR']",$editor);
    }

    public function test_directory_returns_only_primary_voucher_leg_and_edit_modal_shows_counterpart(): void
    {
        $root=dirname(__DIR__,3);
        $directory=file_get_contents($root.'/app/Http/Controllers/Finance/VoucherController.php');
        $ledger=file_get_contents($root.'/app/Http/Controllers/LedgerController.php');
        $view=file_get_contents($root.'/resources/views/admin/finance/voucher/cash_voucher.blade.php');
        self::assertStringContainsString("from('ledgers as earlier')",$directory);
        self::assertStringContainsString("whereColumn('earlier.id','<','ledgers.id')",$directory);
        self::assertStringContainsString("'counter_account_id' => $".'counterAccountId',$ledger);
        self::assertStringContainsString('id="edit_counter_account"',$view);
        self::assertStringContainsString("counterTs?.setValue(counterId,true)",$view);
    }
}
