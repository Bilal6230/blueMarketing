<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class BorAmountWordsPilotContractTest extends TestCase
{
    public function test_bor_add_modal_places_bank_account_before_amount_and_words(): void
    {
        $view=file_get_contents(resource_path('views/admin/finance/voucher/cash_voucher.blade.php'));
        $start=strpos($view, "@if(\$isBankVoucherSeries)");
        $end=strpos($view, "@if(in_array(\$displaySeries ?? '', ['CR','DR','CP','CCR','CDR'], true))", $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $add=substr($view,$start,$end-$start);

        $this->assertStringContainsString("@if(\$displaySeries==='BOR')", $add);
        $this->assertStringContainsString('Debit Account (Bank)', $add);
        $this->assertStringContainsString('id="bor_amount_words"', $add);
        $this->assertStringContainsString('disabled readonly', $add);
        $this->assertStringNotContainsString('name="amount_words"', $add);
        $this->assertLessThan(strpos($add, 'bor-amount-field'), strpos($add, 'bank-posting-account'));
        $this->assertLessThan(strpos($add, 'bor-amount-words'), strpos($add, 'bor-amount-field'));
    }

    public function test_only_bor_add_has_the_special_grid_and_live_read_only_word_display(): void
    {
        $view=file_get_contents(resource_path('views/admin/finance/voucher/cash_voucher.blade.php'));
        $this->assertStringContainsString("{{ \$displaySeries==='BOR' ? ' bor-add-layout' : '' }}", $view);
        $this->assertStringContainsString('grid-template-columns:repeat(3,minmax(0,1fr))', $view);
        $this->assertStringContainsString('.bor-amount-words {', $view);
        $this->assertStringContainsString("function updateBorAmountWords()", $view);
        $this->assertStringContainsString("numberToWords.toWords(amount)", $view);
        $this->assertStringContainsString("on('input change', '#voucherForm #numberInput', updateBorAmountWords)", $view);
        $this->assertStringContainsString("@else\n                                                    {{-- Amount (small) -- original layout for other vouchers --}}", $view);
        $this->assertStringContainsString('id="edit_amount"', $view);
    }
}
