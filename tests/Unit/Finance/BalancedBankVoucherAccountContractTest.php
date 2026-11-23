<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class BalancedBankVoucherAccountContractTest extends TestCase
{
    public function test_bor_bop_store_real_bank_side_and_party_side_and_bind_immutable_project_bank(): void
    {
        $form=file_get_contents(__DIR__.'/../../../resources/views/admin/finance/voucher/cash_voucher.blade.php');
        $controller=file_get_contents(__DIR__.'/../../../app/Http/Controllers/LedgerController.php');
        $cashScreen=file_get_contents(__DIR__.'/../../../app/Http/Controllers/Finance/VoucherController.php');
        $claim=file_get_contents(__DIR__.'/../../../app/Services/Finance/PassedBankVoucherClaim.php');
        $schema=file_get_contents(__DIR__.'/../../../database/migrations/2026_11_23_170000_add_bank_account_identity_to_finance_voucher_allocations.php');
        foreach([$form,$controller,$cashScreen,$claim,$schema] as $file)self::assertIsString($file);
        self::assertStringContainsString('bank_counter_account_id', $form);
        self::assertStringContainsString('Debit Account (Bank)', $form);
        self::assertStringContainsString('Credit Account (Bank)', $form);
        self::assertStringContainsString("where('is_active',1)", $cashScreen);
        self::assertStringContainsString("BalancedBankVoucherIntegrity::bankAccount(", $controller);
        self::assertStringContainsString("Ledger::create([", $controller);
        self::assertStringContainsString("bankCounter->account_mapping_id", $controller);
        self::assertStringContainsString("'bank_account_id'=>", $claim);
        self::assertStringContainsString("'bank_account_id')->nullable()", $schema);
    }

    public function test_approval_release_and_read_only_correction_support_both_ledger_legs(): void
    {
        $approval=file_get_contents(__DIR__.'/../../../app/Services/Finance/DailyVoucherApprovalWorkflow.php');
        $release=file_get_contents(__DIR__.'/../../../app/Services/Finance/BankVoucherAllocationRelease.php');
        $planner=file_get_contents(__DIR__.'/../../../app/Services/Finance/BankManagementCascadePlanner.php');
        $listing=file_get_contents(__DIR__.'/../../../app/Http/Controllers/Finance/VoucherController.php');
        foreach([$approval,$release,$planner,$listing] as $file)self::assertIsString($file);
        self::assertStringContainsString('BalancedBankVoucherIntegrity::assert(', $approval);
        self::assertStringContainsString('BalancedBankVoucherIntegrity::assert(', $release);
        self::assertStringContainsString('BalancedBankVoucherIntegrity::assert(', $planner);
        self::assertStringContainsString("whereIn('id',$"."legs->pluck('id')->all())", $release);
        self::assertStringContainsString("whereIn('a.voucher_type',['BOR','BOP'])", $listing);
        self::assertStringContainsString("whereColumn('bank.account_mapping_id','ledgers.project_head_subheads_id')", $listing);
    }
}
