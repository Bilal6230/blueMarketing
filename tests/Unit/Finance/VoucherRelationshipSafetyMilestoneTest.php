<?php

namespace Tests\Unit\Finance;

use PHPUnit\Framework\TestCase;

final class VoucherRelationshipSafetyMilestoneTest extends TestCase
{
    private function source(string $path): string
    {
        $content=file_get_contents(dirname(__DIR__,3).'/'.$path);
        self::assertIsString($content);
        return $content;
    }

    public function test_schema_audit_reports_missing_lifecycle_columns_and_real_foreign_keys_without_writing(): void
    {
        $audit=$this->source('app/Console/Commands/AuditVoucherSchema.php');
        self::assertStringContainsString('finance:audit-voucher-schema',$audit);
        self::assertStringContainsString('information_schema.COLUMNS',$audit);
        self::assertStringContainsString('information_schema.KEY_COLUMN_USAGE',$audit);
        self::assertStringContainsString('tables_with_neither',$audit);
        self::assertStringContainsString('orphan_count',$audit);
        self::assertStringContainsString('cross_project_detail_links',$audit);
        self::assertStringContainsString('require-fks', $audit);
        self::assertStringContainsString('Voucher schema integrity gate FAILED', $audit);
        self::assertStringContainsString("'missing_foreign_keys'", $audit);
        foreach (['->insert(', '->update(', '->delete(', 'DB::statement(', 'DB::unprepared('] as $writer) {
            self::assertStringNotContainsString($writer,$audit);
        }
    }

    public function test_foreign_key_migration_restricts_physical_delete_and_aborts_on_orphans(): void
    {
        $migration=$this->source('database/migrations/2026_11_18_150000_enforce_voucher_source_foreign_keys.php');
        foreach ([
            "'journal_voucher_details','journal_voucher_id','journal_vouchers'",
            "'commision_voucher_details','commision_voucher_id','commision_vouchers'",
            "'ledgers','journal_voucher_detail_id','journal_voucher_details'",
            "'ledgers','customer_ledger_id','customer_ledger'",
            "'ledgers','project_head_subheads_id','project_head_subheads'",
            "'journal_voucher_details','account_id','project_head_subheads'",
            "'commision_voucher_details','account_id','project_head_subheads'",
            "'ledger_source_attributions','ledger_id','ledgers'",
        ] as $relationship) self::assertStringContainsString($relationship,$migration);
        self::assertStringContainsString('->restrictOnDelete()->restrictOnUpdate()',$migration);
        self::assertStringContainsString('orphan references',$migration);
        self::assertStringContainsString('cross-project account links',$migration);
        self::assertStringNotContainsString('cascadeOnDelete',$migration);
        self::assertStringNotContainsString('SET FOREIGN_KEY_CHECKS=0',$migration);
    }

    public function test_legacy_journal_and_commission_edits_and_deletes_retire_related_ledger_rows(): void
    {
        $service=$this->source('app/Services/Finance/LinkedVoucherLedgerRetirement.php');
        self::assertStringContainsString("in_array(\$type,['JV','SV','CV'],true)",$service);
        self::assertStringContainsString("->where('type',\$type)->where('type_id',\$voucherId)",$service);
        self::assertStringContainsString("->where('project_id',\$projectId)",$service);
        self::assertStringContainsString("'is_active'=>0",$service);
        self::assertStringContainsString('lockForUpdate()',$service);
        self::assertStringContainsString('Voucher detail/ledger amounts or ownership mismatch',$service);
        self::assertStringNotContainsString('->delete(', $service);
        self::assertStringNotContainsString('->forceDelete(', $service);

        $journal=$this->source('app/Http/Controllers/JournalVoucherController.php');
        // Journal controller supports both JV and SV using the voucher's
        // verified series instead of hard-coding JV.
        self::assertStringContainsString("->retire(\$projectId, \$voucher->type==='SV'?'SV':'JV', (int)\$voucher->id)",$journal);
        self::assertStringContainsString('DB::transaction(function () use ($id, $projectId',$journal);
        self::assertStringNotContainsString('forceDelete(',$journal);

        $commission=$this->source('app/Http/Controllers/CommissionVoucherController.php');
        self::assertSame(2,substr_count($commission,"->retire(\$projectId, 'CV', (int)\$voucher->id)"));
        self::assertStringContainsString('DB::transaction(function () use ($id, $projectId',$commission);
        self::assertStringNotContainsString('forceDelete(',$commission);
    }
    public function test_draft_deletion_archives_the_scoped_row_without_pending_update_or_hard_delete(): void
    {
        $controller=$this->source('app/Http/Controllers/LedgerController.php');
        $start=strpos($controller,'public function draftDestroy(Request $request)');
        $end=strpos($controller,'public function update(Request $request,',$start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $method=substr($controller,$start,$end-$start);
        self::assertStringContainsString("can('finance draft delete')",$method);
        self::assertStringContainsString("->where('project_id',\$projectId)",$method);
        self::assertStringContainsString('DB::transaction(', $method);
        self::assertStringContainsString('->is_active=0',$method);
        self::assertStringContainsString('$draft->delete()',$method);
        self::assertStringNotContainsString('PendingUpdate::create(', $method);
        self::assertStringNotContainsString('forceDelete(', $method);
        self::assertStringContainsString("response()->json(['status'=>'success'",$method);
    }

}
