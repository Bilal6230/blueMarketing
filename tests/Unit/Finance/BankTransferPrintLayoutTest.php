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

    public function test_a5_print_uses_normal_document_flow_to_avoid_repeated_voucher(): void
    {
        $template=file_get_contents(resource_path('views/admin/finance/voucher/bank_transfer_print.blade.php'));
        $this->assertStringContainsString('@page{size:A5 portrait;margin:8mm}', $template);
        $this->assertStringContainsString('position:static!important;display:block!important;', $template);
        $this->assertStringContainsString('width:100%!important;max-width:none!important;', $template);
        $this->assertStringContainsString('height:auto!important;min-height:0!important;max-height:none!important;', $template);
        $this->assertStringContainsString('.toolbar{display:none!important}', $template);
        $this->assertStringContainsString('overflow:visible!important;box-shadow:none!important;', $template);
        $this->assertStringNotContainsString('position:fixed!important', $template);
        $this->assertStringNotContainsString('height:1px!important', $template);
        $this->assertStringNotContainsString('height:297mm!important', $template);
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
