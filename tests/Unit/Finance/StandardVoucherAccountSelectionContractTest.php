<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

/**
 * The default ERP finance path must not render posting controls belonging to
 * the disabled alternate two-leg writer. The same bottom account mapping is
 * consumed by standard CR/DR/BOR/BOP and cash-only CCR/CDR.
 */
final class StandardVoucherAccountSelectionContractTest extends TestCase
{
    public function test_unused_top_debit_and_credit_fields_are_not_rendered_in_standard_erp_mode(): void
    {
        $partial=file_get_contents(__DIR__.'/../../../resources/views/admin/finance/voucher/typed_posting_fields.blade.php');
        $workflow=file_get_contents(__DIR__.'/../../../app/Services/Finance/FinanceWorkflowAccess.php');
        $voucher=file_get_contents(__DIR__.'/../../../resources/views/admin/finance/voucher/cash_voucher.blade.php');
        self::assertIsString($partial);
        self::assertIsString($workflow);
        self::assertIsString($voucher);
        self::assertStringContainsString('FinanceWorkflowAccess::selected()', $partial);
        self::assertStringNotContainsString('FinanceWorkflowAccess::enabled()', $partial);
        self::assertMatchesRegularExpression('/function selected\(\): bool\s*\{\s*return false;\s*\}/', $workflow);
        self::assertStringContainsString("name=\"accounts_id\"", $voucher);
        self::assertStringContainsString("name=\"subaccounts_id\"", $voucher);
        self::assertStringContainsString("name=\"acct_type\"", $voucher);
        self::assertStringContainsString("name=\"payment_type\"", $voucher);
    }

    public function test_standard_cash_and_bank_voucher_writers_use_selected_project_account_mapping(): void
    {
        $ledger=file_get_contents(__DIR__.'/../../../app/Http/Controllers/LedgerController.php');
        $cash=file_get_contents(__DIR__.'/../../../app/Http/Controllers/Finance/CashOnlyVoucherController.php');
        $picker=file_get_contents(__DIR__.'/../../../app/Http/Controllers/Finance/PassedBankSourcePickerController.php');
        self::assertIsString($ledger);
        self::assertIsString($cash);
        self::assertIsString($picker);
        self::assertStringContainsString("->where('head_accounting_id', \$request->accounts_id)", $ledger);
        self::assertStringContainsString("->where('subhead_accounting_id', \$request->subaccounts_id)", $ledger);
        self::assertStringContainsString("->where('project_id', \$selectedProjectId)", $ledger);
        self::assertStringContainsString("'project_head_subheads_id'=>\$account->id", $cash);
        self::assertStringContainsString("'account_mapping_id'", $picker);
    }
}
