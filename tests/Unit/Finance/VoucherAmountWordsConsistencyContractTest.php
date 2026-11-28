<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class VoucherAmountWordsConsistencyContractTest extends TestCase
{
    public function test_cash_bank_add_and_edit_have_live_emphasized_words(): void
    {
        $view=file_get_contents(resource_path('views/admin/finance/voucher/cash_voucher.blade.php'));
        foreach (['voucher-amount-words-layout','voucher-amount-field','voucher_amount_words',
                  'edit_amount_words','updateVoucherAmountWords','updateEditVoucherAmountWords',
                  'font-size:15px','font-weight:600'] as $part) {
            $this->assertStringContainsString($part,$view);
        }
        $this->assertSame(1, substr_count($view,'id="numberInput"'));
        $this->assertStringContainsString('disabled readonly',$view);
        $this->assertStringNotContainsString('id="eWordingAmount"',$view);
    }

    public function test_bank_transfers_and_linked_bank_postings_show_source_words(): void
    {
        $transfer=file_get_contents(resource_path('views/admin/finance/voucher/bank_transfer_workspace.blade.php'));
        $linked=file_get_contents(resource_path('views/admin/finance/voucher/bank_linked_workspace.blade.php'));
        foreach (['id="btAmountWords"','updateBtAmountWords','font-weight:600','disabled readonly'] as $part) {
            $this->assertStringContainsString($part,$transfer);
        }
        $this->assertStringContainsString('id="linkedAmountWords"',$linked);
        $this->assertStringContainsString("numberToWords.toWords(number)",$linked);
    }

    public function test_plot_voucher_amounts_have_read_only_words(): void
    {
        $purchase=file_get_contents(resource_path('views/admin/finance/voucher/plot_purchase_workspace.blade.php'));
        $receipt=file_get_contents(resource_path('views/admin/finance/voucher/plot_receipt_workspace.blade.php'));
        $original=file_get_contents(resource_path('views/admin/finance/voucher/original_ppr_create_modal.blade.php'));
        $this->assertStringContainsString('id="ppTotalWords"',$purchase);
        $this->assertStringContainsString('id="prTotalWords"',$receipt);
        $this->assertStringContainsString('id="prOriginalAmountWords"',$original);
        $this->assertStringContainsString('updateOriginalPprWords',$receipt);
        $this->assertStringContainsString('font-weight:600',$purchase);
        $this->assertStringContainsString('font-weight:600',$receipt);
        $this->assertStringContainsString('font-weight:600',$original);
    }

    public function test_journal_sales_commission_add_edit_show_balanced_total_in_words(): void
    {
        foreach (['journal_voucher','edit_journal_voucher','commision_voucher',
            'edit_commision_voucher'] as $name) {
            $view=file_get_contents(resource_path('views/admin/reports/vouchers/'.$name.'.blade.php'));
            $this->assertStringContainsString('id="journalAmountWords"',$view);
            $this->assertStringContainsString('numberToWords.toWords(wordingAmount)',$view);
            $this->assertStringContainsString('disabled readonly',$view);
            $this->assertStringContainsString('font-weight:600',$view);
        }
    }

    public function test_drafts_and_extra_charges_use_disabled_words_instead_of_plain_text(): void
    {
        $draft=file_get_contents(resource_path('views/admin/finance/voucher/draft_voucher.blade.php'));
        $extra=file_get_contents(resource_path('views/admin/booking/extra_charge.blade.php'));
        $this->assertStringContainsString('id="draftAmountWords"',$draft);
        $this->assertStringContainsString('id="extraAmountWords"',$extra);
        $this->assertStringContainsString('font-weight:600',$draft);
        $this->assertStringContainsString('font-weight:600',$extra);
        $this->assertStringNotContainsString('id="wordingAmount"',$draft);
        $this->assertStringNotContainsString('id="wordingAmount"',$extra);
    }
}
