<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

final class LedgerConsolidationSafetyContractTest extends TestCase
{
    private function code(string $name): string
    {
        $source=file_get_contents(base_path($name));
        self::assertIsString($source);
        return str_replace(["\r\n","\r"],"\n",$source);
    }

    public function test_preflight_does_not_write_or_modify_the_existing_database(): void
    {
        $source=$this->code('app/Console/Commands/AuditLedgerConsolidation.php');
        self::assertStringContainsString('finance:audit-ledger-consolidation',$source);
        self::assertStringContainsString('--database=',$source);
        self::assertStringContainsString('--strict',$source);
        self::assertStringContainsString('read_only',$source);
        self::assertStringContainsString('finance_posting_lines',$source);
        self::assertStringContainsString('legacy_ledger_id',$source);
        foreach(['insert(', 'insertGetId(', 'update(', 'delete(', 'truncate(', 'drop(', 'unprepared('] as $mutation){
            self::assertStringNotContainsString('->'.$mutation,$source);
        }
        self::assertStringNotContainsString('Schema::create(',$source);
        self::assertStringNotContainsString('DB::statement(',$source);
    }

    public function test_cp_to_dr_requires_explicit_voucher_source_identity(): void
    {
        $source=$this->code('app/Console/Commands/AuditLedgerConsolidation.php');
        self::assertStringContainsString("'UNCLASSIFIED_CP'",$source);
        self::assertStringContainsString("'project_voucher_display_numbers'",$source);
        self::assertStringContainsString("'customer_ledger'",$source);
        self::assertStringContainsString("'missing_or_foreign_source'",$source);
        self::assertStringNotContainsString("UPDATE ledgers SET type='DR'",$source);
    }

    public function test_existing_schema_supports_a_ledger_link_without_another_table(): void
    {
        $typed=$this->code('database/migrations/2026_11_17_100000_create_finance_posting_lines_table.php');
        self::assertStringContainsString('legacy_ledger_id',$typed);
        $ledger=$this->code('database/migrations/2024_01_14_150839_create_ledgers_table.php');
        foreach(['type','type_id','project_head_subheads_id','amount_in','amount_out','is_active'] as $column){
            self::assertStringContainsString($column,$ledger);
        }
    }
}
