<?php

namespace Tests\Feature;

use App\Services\Finance\V2ModelSchemaContract;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Only isolated SQLite :memory: fixture, never the user's MySQL rehearsal. */
class DatabaseV2ModelSchemaContractTest extends TestCase
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

    public function test_isolated_v2_model_contract_identifies_real_unmapped_legacy_attributes(): void
    {
        (require base_path('database/v2_migrations/2026_10_30_000010_create_v2_bank_lookup.php'))->up();
        (require base_path('database/v2_migrations/2026_10_30_000020_create_v2_legacy_domain_baseline.php'))->up();

        $findings = (new V2ModelSchemaContract())->inspect();
        $this->assertContains('site_id', $findings['journal_vouchers'] ?? []);
        $this->assertContains('journal_voucher_detail_id', $findings['ledgers'] ?? []);
        $this->assertArrayNotHasKey('journal_voucher_details', $findings);
        $this->assertArrayNotHasKey('project_head_subheads', $findings);
    }

    public function test_financial_model_relationship_fks_exist_in_isolated_v2_baseline(): void
    {
        (require base_path('database/v2_migrations/2026_10_30_000010_create_v2_bank_lookup.php'))->up();
        (require base_path('database/v2_migrations/2026_10_30_000020_create_v2_legacy_domain_baseline.php'))->up();

        $this->assertSame([], (new V2ModelSchemaContract())->inspectRelationships());
    }

    public function test_contract_refuses_non_v2_sqlite_file_database_without_reading_data(): void
    {
        config()->set('database.connections.sqlite.database', '/tmp/not-a-v2-database.sqlite');
        DB::purge('sqlite');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('isolated V2');
        (new V2ModelSchemaContract())->inspect();
    }
}
