<?php

namespace Tests\Unit\Finance;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class BankTransferPrintLayoutTest extends TestCase
{
    public function test_bank_deposit_and_withdrawal_share_one_print_safe_template(): void
    {
        $template=file_get_contents(resource_path('views/admin/finance/voucher/bank_transfer_print.blade.php'));
        $compiled=Blade::compileString($template);
        $this->assertStringContainsString('Bank Deposit Voucher',$compiled);
        $this->assertStringContainsString('Bank Withdrawal Voucher',$compiled);
        $this->assertStringContainsString('voucher_number',$compiled);
        $this->assertStringContainsString('posting_batch_id',$compiled);
        $this->assertStringContainsString('Prepared by',$template);
        $this->assertStringContainsString('Authorized by',$template);
    }

    public function test_print_sheet_is_removed_from_page_flow_with_single_a4_inset(): void
    {
        $template=file_get_contents(resource_path('views/admin/finance/voucher/bank_transfer_print.blade.php'));
        $this->assertStringContainsString('@page{size:A4 portrait;margin:0}', $template);
        $this->assertStringContainsString('.sheet{position:absolute!important;top:12mm!important;left:12mm!important;right:12mm!important;', $template);
        $this->assertStringContainsString('margin:0!important;padding:0!important;box-shadow:none!important', $template);
        $this->assertStringContainsString('.toolbar{display:none!important}', $template);
        $this->assertStringContainsString('break-inside:avoid', $template);
        $this->assertStringContainsString('height:0!important;min-height:0!important', $template);
        $this->assertStringNotContainsString('padding:12mm;box-shadow:none}@page', $template);
    }

    public function test_print_layout_does_not_change_financial_amounts_or_lines(): void
    {
        $template=file_get_contents(resource_path('views/admin/finance/voucher/bank_transfer_print.blade.php'));
        $this->assertStringContainsString('@foreach($lines as $line)', $template);
        $this->assertStringContainsString('$line->debit', $template);
        $this->assertStringContainsString('$line->credit', $template);
        $this->assertStringContainsString('$source->amount', $template);
    }
}
