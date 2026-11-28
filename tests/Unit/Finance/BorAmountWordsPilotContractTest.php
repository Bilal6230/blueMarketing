<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class BorAmountWordsPilotContractTest extends TestCase
{
    public function test_approved_layout_is_shared_by_cash_and_bank_add_forms(): void
    {
        $view=file_get_contents(resource_path('views/admin/finance/voucher/cash_voucher.blade.php'));
        $this->assertStringContainsString('row g-2 align-items-end voucher-amount-words-layout', $view);
        $this->assertStringContainsString('class="col-12 col-md-6 col-lg-4 voucher-amount-field"', $view);
        $this->assertStringContainsString('id="voucher_amount_words"', $view);
        $this->assertStringContainsString('disabled readonly', $view);
        $this->assertStringContainsString('bank-posting-account', $view);
        $this->assertStringContainsString('standard-counter-account', $view);
        $this->assertSame(1,substr_count($view,'id="numberInput"'));
        $this->assertLessThan(strpos($view, 'voucher-amount-field', strpos($view,'<form id="voucherForm"')),
            strpos($view,'bank-posting-account',strpos($view,'<form id="voucherForm"')));
    }

    public function test_live_words_are_read_only_emphasized_in_add_and_edit(): void
    {
        $view=file_get_contents(resource_path('views/admin/finance/voucher/cash_voucher.blade.php'));
        $this->assertStringContainsString('id="edit_amount_words"', $view);
        $this->assertStringContainsString('function updateVoucherAmountWords()', $view);
        $this->assertStringContainsString('function updateEditVoucherAmountWords()', $view);
        $this->assertStringContainsString("on('input change', '#voucherForm #numberInput', updateVoucherAmountWords)", $view);
        $this->assertStringContainsString("on('input change', '#edit-voucher-form #edit_amount', updateEditVoucherAmountWords)", $view);
        $this->assertStringContainsString('font-size:15px;', $view);
        $this->assertStringContainsString('font-weight:600;', $view);
        $this->assertStringNotContainsString('id="eWordingAmount"', $view);
    }
}
