<?php

namespace Tests\Feature\Finance;

use Tests\TestCase;

final class NormalBankTransferModernUiTest extends TestCase
{
    public function test_bank_deposit_and_withdrawal_share_finance_modal_tomselect_and_toast_patterns(): void
    {
        $view = file_get_contents(resource_path('views/admin/finance/voucher/bank_transfer_workspace.blade.php'));
        $feedback = file_get_contents(public_path('js/finance-voucher-feedback.js'));

        foreach (['bankTransferCreateModal', 'bankTransferViewModal', 'bankTransferReverseModal'] as $id) {
            $this->assertMatchesRegularExpression(
                '/class="modal fade fvc-modern-modal bt-modern-modal"[^>]*id="'.preg_quote($id, '/').'"/',
                $view
            );
        }

        foreach (['transferBankFilter', 'cashAccount', 'bankAccount', 'chequeSource'] as $field) {
            $this->assertMatchesRegularExpression(
                '/<select[^>]*id="'.preg_quote($field, '/').'"[^>]*class="form-control fvc-tom-select"/',
                $view
            );
        }

        $this->assertStringContainsString('feedback.enhanceSelects(', $view);
        $this->assertStringContainsString("syncSelect('#chequeSource')", $view);
        $this->assertStringContainsString("syncSelect('#cashAccount,#bankAccount,#chequeSource')", $view);
        $this->assertStringContainsString('feedback.toast(type,text)', $view);
        $this->assertStringContainsString('feedback.error(messageOrXhr,defaultMessage)', $view);
        $this->assertStringContainsString(".bt-modern-modal').length?'body':null", $feedback);
        $this->assertStringNotContainsString('window.alert(', $view);
        $this->assertStringNotContainsString('Swal.fire(', $view);
        $this->assertStringNotContainsString('window.confirm(', $view);

        // This batch must not change posted financial source and reversal routes.
        $this->assertStringContainsString("route('finance.voucher.transfer.post'", $view);
        $this->assertStringContainsString("route('finance.voucher.transfer.history'", $view);
        $this->assertStringContainsString("route('finance.voucher.transfer.sources'", $view);
        $this->assertStringContainsString("id=\"bankTransferReverseForm\"", $view);
    }
}
