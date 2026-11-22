<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class RemainingVoucherNumberMilestonesContractTest extends TestCase
{
    public function test_bank_transfer_modals_preview_the_next_independent_number_without_consuming_it(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/Finance/VoucherController.php'));
        $view = file_get_contents(base_path('resources/views/admin/finance/voucher/bank_transfer_workspace.blade.php'));
        $permissions = file_get_contents(base_path('app/Support/Finance/FinanceActionPermission.php'));

        self::assertStringContainsString("name('finance.voucher.transfer.next-number')", $routes);
        self::assertStringContainsString("function transferVoucherNextNumber(", $controller);
        self::assertStringContainsString('->preview($projectId,$series)', $controller);
        self::assertStringContainsString("'finance.voucher.transfer.next-number'=>'view'", $permissions);
        self::assertStringContainsString('id="btVoucherNumber"', $view);
        self::assertStringContainsString('readonly aria-readonly="true"', $view);
        self::assertStringContainsString("data-next-number-url=", $view);
        self::assertStringContainsString("data.voucher_number", $view);
    }

    public function test_party_ledger_uses_verified_source_numbers_instead_of_inferring_jv_header_from_type_id(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/AccountingController.php'));
        $report = file_get_contents(base_path('resources/views/admin/finance/reports/party_ledger_report.blade.php'));
        $resolver = file_get_contents(base_path('app/Services/Finance/LedgerVoucherDisplayService.php'));

        self::assertStringContainsString('LedgerVoucherDisplayService::class', $controller);
        self::assertStringContainsString('$ledger->display_voucher_number', $report);
        self::assertStringNotContainsString('JournalVoucher::where(', $report);
        self::assertStringContainsString("'l.customer_ledger_id'", $resolver);
        self::assertStringContainsString('journal_voucher_detail_id', $resolver);
        self::assertStringContainsString('commision_voucher_detail_id', $resolver);
        self::assertStringContainsString("'SV-'.(int)\$header", $resolver);
        self::assertStringContainsString('CommissionVoucherNumber::display($header)', $resolver);
    }

    public function test_printed_cash_bank_vouchers_show_their_actual_number_title_and_amount(): void
    {
        $view = file_get_contents(base_path('resources/views/admin/finance/voucher/print-eng.blade.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/Finance/VoucherController.php'));
        self::assertStringContainsString("'BOR'=>'Bank Receipt Voucher'", $view);
        self::assertStringContainsString("'BOP'=>'Bank Payment Voucher'", $view);
        self::assertStringContainsString("['CP','DR','CDR','BOP','BO']", $view);
        self::assertStringContainsString('LedgerVoucherDisplayService::class', $controller);
    }

    public function test_commission_form_and_new_header_use_the_same_number_style(): void
    {
        $identity = file_get_contents(base_path('app/Services/Finance/ProjectVoucherDisplayIdentity.php'));
        self::assertStringContainsString("'CV' => CommissionVoucherNumber::display((int)\$match[1])", $identity);
    }
}
