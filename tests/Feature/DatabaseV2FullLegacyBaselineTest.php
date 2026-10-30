<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Isolated SQLite verification only, never touches an existing database. */
class DatabaseV2FullLegacyBaselineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.sqlite.foreign_key_constraints', true);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::statement('PRAGMA foreign_keys = ON');
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_complete_source_schema_is_recreated_with_typed_fks_and_v2_columns(): void
    {
        $banks = require base_path('database/v2_migrations/2026_10_30_000010_create_v2_bank_lookup.php');
        $baseline = require base_path('database/v2_migrations/2026_10_30_000020_create_v2_legacy_domain_baseline.php');
        $finance = require base_path('database/v2_migrations/2026_10_30_000100_create_v2_finance_posting_core.php');

        $banks->up();
        $baseline->up();

        // The 57 old tables include the old Laravel migration-history table.
        // New V2 creates 56 app entities, banks and legacy_migration_history archive.
        $all = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
        $names = array_map(static fn ($x) => $x->name, $all);
        $this->assertCount(58, array_filter($names, fn ($name) => $name !== 'sqlite_sequence'));
        $this->assertTrue(Schema::hasTable('ledgers'));
        $this->assertTrue(Schema::hasColumn('sites', 'project_head_subhead_id'));
        $this->assertTrue(Schema::hasColumn('pending_updates', 'remarks'));
        $this->assertTrue(Schema::hasColumn('labours', 'deleted_at'));
        $this->assertFalse(Schema::hasColumn('ledgers', 'deleted_at'));
        $this->assertFalse(Schema::hasTable('migrations'));
        $this->assertTrue(Schema::hasTable('legacy_migration_history'));

        $ledgersFks = DB::select("PRAGMA foreign_key_list('ledgers')");
        $targets = array_map(static fn ($x) => $x->table, $ledgersFks);
        $this->assertContains('project_head_subheads', $targets);
        $this->assertContains('customer_ledger', $targets);
        $this->assertContains('charge_types', $targets);

        $finance->up();
        $this->assertTrue(Schema::hasTable('finance_posting_batches'));
        $this->assertTrue(Schema::hasTable('finance_posting_lines'));
        $this->assertTrue(Schema::hasTable('finance_journal_line_sources'));

        $finance->down();
        $baseline->down();
        $banks->down();
        $this->assertFalse(Schema::hasTable('ledgers'));
        $this->assertFalse(Schema::hasTable('banks'));
    }

    public function test_missing_bank_parent_fails_closed_without_creating_any_business_tables(): void
    {
        $baseline = require base_path('database/v2_migrations/2026_10_30_000020_create_v2_legacy_domain_baseline.php');
        try {
            $baseline->up();
            $this->fail('Expected first V2 lookup prerequisite');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('bank lookup', $e->getMessage());
        }
        $this->assertFalse(Schema::hasTable('ledgers'));
    }
}
