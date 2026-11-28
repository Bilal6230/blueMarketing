<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class VoucherTerminologyAndLayoutContractTest extends TestCase
{
    public function test_ccr_and_cdr_display_names_are_consistent_across_navigation_editor_and_print(): void
    {
        $paths = [
            resource_path('views/admin/layouts/sidebar-menu.blade.php'),
            resource_path('views/admin/finance/voucher/daily_directory.blade.php'),
            app_path('Http/Controllers/Finance/VoucherController.php'),
            resource_path('views/admin/finance/voucher/print-eng.blade.php'),
        ];
        foreach ($paths as $path) {
            $text = file_get_contents($path);
            $this->assertIsString($text);
            $this->assertStringNotContainsString('Cash Credit (CCR)', $text, $path);
            $this->assertStringNotContainsString('Cash Debit (CDR)', $text, $path);
            $this->assertStringNotContainsString('Cash Credit Voucher', $text, $path);
            $this->assertStringNotContainsString('Cash Debit Voucher', $text, $path);
            $this->assertStringContainsString('Cash Receive Voucher', $text, $path);
            $this->assertStringContainsString('Cash Payment Voucher', $text, $path);
        }
    }

    public function test_shared_add_and_edit_forms_place_account_hierarchy_before_amount(): void
    {
        $add = file_get_contents(resource_path('views/admin/finance/voucher/cash_voucher.blade.php'));
        $edit = file_get_contents(resource_path('views/admin/finance/voucher/rejected_cash_revision.blade.php'));

        $this->assertStringContainsString('> :has(#acct_type){order:4}', $add);
        $this->assertStringContainsString('> :has(#accounts_id){order:5}', $add);
        $this->assertStringContainsString('> :has(#subaccounts_id){order:6}', $add);
        $this->assertStringContainsString('> .voucher-amount-field{order:7}', $add);

        $this->assertStringContainsString(':has(#cr-revise-type){order:4}', $edit);
        $this->assertStringContainsString(':has(#cr-revise-head){order:5}', $edit);
        $this->assertStringContainsString(':has(#cr-revise-account){order:6}', $edit);
        $this->assertStringContainsString(':has(#cr-revise-amount){order:7}', $edit);
        $this->assertStringContainsString('CCR', $edit);
        $this->assertStringContainsString('CDR', $edit);
    }
}
