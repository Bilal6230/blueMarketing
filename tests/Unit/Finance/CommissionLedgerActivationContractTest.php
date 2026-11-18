<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class CommissionLedgerActivationContractTest extends TestCase
{
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
