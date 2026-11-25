<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class CommissionVoucherDetailRelationContractTest extends TestCase
{
    public function test_commission_ledger_detail_link_is_explicit_and_backward_compatible(): void
    {
        $root=dirname(__DIR__,3);
        $migration=file_get_contents($root.'/database/migrations/2026_11_18_220000_link_commission_details_to_ledger.php');
        $ledger=file_get_contents($root.'/app/Models/Ledger.php');
        $controller=file_get_contents($root.'/app/Http/Controllers/CommissionVoucherController.php');
        $retirement=file_get_contents($root.'/app/Services/Finance/LinkedVoucherLedgerRetirement.php');
        foreach ([$migration,$ledger,$controller,$retirement] as $source) self::assertIsString($source);
        self::assertStringContainsString('commision_voucher_detail_id',$migration);
        self::assertStringContainsString('restrictOnDelete()',$migration);
        self::assertStringNotContainsString('Schema::create(',$migration);
        self::assertStringContainsString('commision_voucher_detail_id',$ledger);
        self::assertStringContainsString("\$attributes['commision_voucher_detail_id'] = \$detail->id",$controller);
        self::assertStringContainsString('Commission ledger detail account or amount mismatch',$retirement);
        self::assertStringContainsString('if (!$detailId) continue;',$retirement);
    }

    public function test_commission_draft_noop_guard_runs_before_detail_archiving(): void
    {
        $root=dirname(__DIR__,3);
        $source=file_get_contents($root.'/app/Services/Finance/CommissionDraftWorkflow.php');
        self::assertIsString($source);
        $start=strpos($source,'public function updateDraft(');
        $end=strpos($source,'public function cancelDraft(',$start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $update=substr($source,$start,$end-$start);
        $guard=strpos($update,'VoucherUnchangedUpdateDetector::class');
        // Draft edits preserve existing detail/ledger IDs. Only a removed
        // detail is soft-deleted; the no-op guard must run before this path.
        $archive=strpos($update,"->whereNull('deleted_at')->update(['deleted_at'=>now(),'updated_at'=>now()])");
        $linkedDraftUpdate=strpos($update,"->where('commision_voucher_id',\$sourceId)->whereNull('deleted_at')");
        self::assertNotFalse($guard);
        self::assertNotFalse($archive);
        self::assertNotFalse($linkedDraftUpdate);
        self::assertLessThan($archive,$guard);
        self::assertStringContainsString("if (isset(\$submittedIds[(int)\$detail->id])) continue;", $update);
        self::assertStringContainsString("->where('commision_voucher_detail_id',\$detailId)->where('is_active',0)", $update);
        self::assertStringContainsString("->update(\$values);", $update);
        self::assertStringContainsString("->where('commision_voucher_id', \$sourceId)->whereNull('deleted_at')",$update);
        self::assertStringContainsString("array_key_exists('reference',\$metadata)",$update);
        self::assertStringContainsString("array_key_exists('description',\$metadata)",$update);
    }
}
