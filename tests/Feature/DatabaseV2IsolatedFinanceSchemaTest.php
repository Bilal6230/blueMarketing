<?php

namespace Tests\Feature;

use Database\Seeders\V2LegacyBanksSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseV2IsolatedFinanceSchemaTest extends TestCase
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

    private function migrateIsolatedParents(): void
    {
        foreach ([
            'projects', 'users', 'project_head_subheads',
            'journal_vouchers', 'journal_voucher_details',
            'commision_vouchers', 'stock_entries', 'stock_bills',
            'customer_ledger', 'booking_vouchers', 'labour_ledgers',
        ] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
            });
        }
    }

    private function runMigration(string $name): object
    {
        return require base_path('database/v2_migrations/' . $name);
    }

    public function test_v2_core_requires_baseline_before_creating_any_tables(): void
    {
        $migration = $this->runMigration('2026_10_30_000100_create_v2_finance_posting_core.php');
        try {
            $migration->up();
            $this->fail('Expected missing baseline rejection');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('V2 baseline must exist first', $e->getMessage());
        }
        $this->assertFalse(Schema::hasTable('finance_posting_batches'));
    }

    public function test_v2_finance_source_fks_and_unique_detail_identity(): void
    {
        $this->migrateIsolatedParents();
        $migration = $this->runMigration('2026_10_30_000100_create_v2_finance_posting_core.php');
        $migration->up();

        DB::table('projects')->insert(['id' => 1]);
        DB::table('users')->insert(['id' => 1]);
        DB::table('project_head_subheads')->insert(['id' => 101]);
        DB::table('journal_vouchers')->insert(['id' => 55]);
        DB::table('journal_voucher_details')->insert(['id' => 550]);
        DB::table('stock_entries')->insert(['id' => 55]);
        DB::table('finance_posting_batches')->insert([
            'id' => 1, 'project_id' => 1, 'voucher_series' => 'JV',
            'effective_date' => '2026-10-30',
        ]);
        DB::table('finance_posting_lines')->insert([
            'id' => 5, 'posting_batch_id' => 1,
            'project_head_subhead_id' => 101, 'debit' => 100, 'credit' => 0,
        ]);
        DB::table('finance_posting_source_claims')->insert([
            'posting_batch_id' => 1, 'source_kind' => 'journal',
        ]);
        DB::table('finance_posting_journal_sources')->insert([
            'posting_batch_id' => 1, 'journal_voucher_id' => 55,
        ]);
        DB::table('finance_journal_line_sources')->insert([
            'posting_line_id' => 5, 'journal_voucher_detail_id' => 550,
        ]);
        $this->assertDatabaseCount('finance_posting_journal_sources', 1);
        $this->assertDatabaseCount('finance_posting_source_claims', 1);
        // A valid Stock Entry with the SAME numeric ID must not attach to
        // the Journal batch: the composite source claim rejects the family.
        try {
            DB::table('finance_posting_stock_entry_sources')->insert([
                'posting_batch_id' => 1, 'stock_entry_id' => 55,
            ]);
            $this->fail('Cross-family source collision unexpectedly accepted');
        } catch (QueryException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_stock_entry_sources', 0);

        $this->assertDatabaseCount('finance_journal_line_sources', 1);

        try {
            DB::table('finance_journal_line_sources')->insert([
                'posting_line_id' => 6, 'journal_voucher_detail_id' => 550,
            ]);
            $this->fail('Expected invalid foreign key/duplicate identity rejection');
        } catch (QueryException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        try {
            DB::table('finance_posting_stock_entry_sources')->insert([
                'posting_batch_id' => 1, 'stock_entry_id' => 999,
            ]);
            $this->fail('Expected FK rejection for nonexistent stock source');
        } catch (QueryException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        $migration->down();
        $this->assertFalse(Schema::hasTable('finance_posting_batches'));
        $this->assertTrue(Schema::hasTable('journal_vouchers'));
    }

    public function test_bank_seed_preserves_ids_and_is_idempotent(): void
    {
        $migration = $this->runMigration('2026_10_30_000010_create_v2_bank_lookup.php');
        $migration->up();
        $seeder = new V2LegacyBanksSeeder();
        $seeder->run();
        $seeder->run();
        $this->assertSame(27, DB::table('banks')->count());
        $this->assertSame('Bank Alfalah', DB::table('banks')->where('id', 1)->value('display_name'));
        $this->assertSame('Bank Al-Habib', DB::table('banks')->where('id', 27)->value('display_name'));

        DB::table('banks')->where('id', 1)->update(['display_name' => 'Changed']);
        $this->expectException(\RuntimeException::class);
        $seeder->run();
    }
}
