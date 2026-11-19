<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class CommissionLedgerActivationContractTest extends TestCase
{
    public function test_approval_uses_fk_identity_not_jv_numeric_overlap_and_returns_json_feedback(): void
    {
        $root=dirname(__DIR__,3);
        $workflow=file_get_contents($root.'/app/Services/Finance/CommissionDraftWorkflow.php');
        $controller=file_get_contents($root.'/app/Http/Controllers/CommissionVoucherController.php');
        $view=file_get_contents($root.'/resources/views/admin/reports/vouchers/edit_commision_voucher.blade.php');
        self::assertIsString($workflow);
        self::assertIsString($controller);
        self::assertIsString($view);
        $approval=substr($workflow,strpos($workflow,'public function approveAndPost('));
        self::assertStringNotContainsString("where('type', 'JV')",$approval);
        self::assertStringContainsString('->assertDraftCanReplace($projectId,$sourceId)',$approval);
        $controllerApproval=substr($controller,strpos($controller,'public function approve('),
            strpos($controller,'private function isMergedTypedCommission(')
                - strpos($controller,'public function approve('));
        self::assertStringContainsString("response()->json(['status'=>'error','message'=>\$reason],409)",$controllerApproval);
        self::assertStringContainsString("'status'=>'success'",$controllerApproval);
        self::assertStringNotContainsString('abort(409,',$controllerApproval);
        self::assertStringContainsString('id="commissionApproveForm"',$view);
        self::assertStringContainsString("submit.fvcCommissionApprove",$view);
        self::assertStringContainsString("headers:{Accept:'application/json'}",$view);
        self::assertStringContainsString("$('#errorModal').modal('show')",$view);
        self::assertStringNotContainsString("projectID: 3",$view);
    }

    public function test_draft_create_edit_approval_and_reverse_share_fk_linked_ledger_lifecycle(): void
    {
        $root=dirname(__DIR__,3);
        $draft=file_get_contents($root.'/app/Services/Finance/CommissionDraftWorkflow.php');
        $reverse=file_get_contents($root.'/app/Services/Finance/VoucherLifecycleService.php');
        $sync=file_get_contents($root.'/app/Services/Finance/CommissionLedgerSynchronizer.php');
        $backfill=file_get_contents($root.'/app/Console/Commands/SyncCommissionDraftLedgers.php');
        self::assertIsString($draft);
        self::assertIsString($reverse);
        self::assertIsString($sync);
        self::assertIsString($backfill);

        self::assertStringContainsString('->ensureDraft($projectId,(int)$id,$actorId)',$draft);
        self::assertStringContainsString('->ensureDraft($projectId,$sourceId)',$draft);
        self::assertStringContainsString('->assertDraftCanReplace($projectId,$sourceId)',$draft);
        self::assertStringContainsString('->activateApproved(',$draft);
        self::assertStringContainsString('->deactivate($projectId,$sourceId)',$reverse);
        self::assertStringContainsString("'commision_voucher_detail_id'=>(int)\$detail->id",$sync);
        self::assertStringContainsString("REFERENCED_TABLE_NAME=? AND REFERENCED_COLUMN_NAME=?",$sync);
        self::assertStringContainsString("where('is_active',0)->update(['is_active'=>1",$sync);
        self::assertStringContainsString('finance:sync-commission-draft-ledgers',$backfill);
        self::assertStringContainsString('{--apply :',$backfill);
    }
}
