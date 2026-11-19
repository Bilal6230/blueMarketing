<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

/** Guards the same-route AJAX lifecycle and current-leg reporting across bank series. */
final class BankTransferCorrectionContractTest extends TestCase
{
    public function test_bank_transfer_edit_is_project_scoped_same_batch_and_rejects_linked_cheque_mutation(): void
    {
        $root=dirname(__DIR__,3);
        $editor=file_get_contents($root.'/app/Services/Finance/BankTransferEditor.php');
        $controller=file_get_contents($root.'/app/Http/Controllers/Finance/VoucherController.php');
        $routes=file_get_contents($root.'/routes/web.php');
        $report=file_get_contents($root.'/app/Services/Finance/BankTransferReconciliation.php');
        foreach([$editor,$controller,$routes,$report] as $code)self::assertIsString($code);
        self::assertStringContainsString("where('id',\$batchId)",$editor);
        self::assertStringContainsString("where('project_id',\$projectId)",$editor);
        self::assertStringContainsString('linked_customer_ledger_id!==null',$editor);
        self::assertStringContainsString("where('is_active',1)",$editor);
        self::assertStringContainsString("'is_active'=>0",$editor);
        self::assertStringContainsString("'is_active'=>1",$editor);
        self::assertStringNotContainsString('reversePostedBatch(',$editor);
        self::assertStringContainsString('function updateTransferVoucher(',$controller);
        self::assertStringContainsString('function transferVoucherDetails(',$controller);
        self::assertStringContainsString("'updateTransferVoucher'",$routes);
        self::assertStringContainsString("'transferVoucherDetails'",$routes);
        self::assertStringContainsString("'deleteTransferVoucher'",$routes);
        self::assertStringContainsString('function deleteTransferVoucher(',$controller);
        self::assertStringContainsString('public function delete(',$editor);
        self::assertStringContainsString("where('line.is_active',1)",$report);
    }

    public function test_manual_bank_transfer_modal_reuses_ajax_ui_and_refreshes_without_full_page_navigation(): void
    {
        $root=dirname(__DIR__,3);
        $view=file_get_contents($root.'/resources/views/admin/finance/voucher/bank_transfer_workspace.blade.php');
        self::assertIsString($view);
        self::assertStringContainsString('data-update-base=',$view);
        self::assertStringContainsString('bt-edit',$view);
        self::assertStringContainsString("editingBatch?'PUT':'POST'",$view);
        self::assertStringContainsString("'/details'",$view);
        self::assertStringContainsString("$('#bankTransferCreateModal').modal('hide')",$view);
        self::assertStringContainsString('loadHistory();',$view);
        self::assertStringContainsString('bankTransferDeleteModal',$view);
        self::assertStringContainsString("type:'DELETE'",$view);
        self::assertStringContainsString('bt-delete',$view);
        self::assertStringContainsString("showError($('#transferFormError')",$view);
    }
}
