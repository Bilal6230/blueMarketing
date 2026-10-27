<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\FinancePostingSchemaGate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class FinancePostingSchemaGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDefaultConnection() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->fail('SQLite :memory: is required.');
        }
        Schema::dropAllTables();
    }

    public function test_absent_tables_remain_explicit_legacy_mode(): void
    {
        $this->assertSame('legacy', app(FinancePostingSchemaGate::class)->mode());
        $this->assertFalse(app(FinancePostingSchemaGate::class)->isReady());
    }

    public function test_single_metadata_table_blocks_even_form_generation(): void
    {
        Schema::create('ledger_source_attributions', function (Blueprint $t) {$t->id();});
        $this->expectException(\RuntimeException::class);
        app(FinancePostingSchemaGate::class)->isReady();
    }

    public function test_two_empty_tables_do_not_count_as_safe_typed_mode(): void
    {
        Schema::create('ledger_source_attributions', function (Blueprint $t) {$t->id();});
        Schema::create('financial_posting_requests', function (Blueprint $t) {$t->id();});
        $this->expectException(\RuntimeException::class);
        app(FinancePostingSchemaGate::class)->mode();
    }

    public function test_approved_sqlite_fixture_exposes_typed_mode(): void
    {
        $migration = require base_path('database/migrations/2026_10_27_000001_create_financial_source_identity_tables.php');
        $migration->up();
        $this->assertSame('typed', app(FinancePostingSchemaGate::class)->mode());
        $this->assertTrue(app(FinancePostingSchemaGate::class)->isReady());
    }

    public function test_missing_unique_request_constraint_blocks_typed_mode(): void
    {
        $migration = require base_path('database/migrations/2026_10_27_000001_create_financial_source_identity_tables.php');
        $migration->up();
        Schema::drop('financial_posting_requests');
        Schema::create('financial_posting_requests', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->string('source_kind');
            $t->string('request_key'); $t->char('payload_hash',64);
            $t->string('status'); $t->unsignedBigInteger('voucher_id')->nullable();
            $t->timestamps();
        });
        $this->expectException(\RuntimeException::class);
        app(FinancePostingSchemaGate::class)->mode();
    }

    public function test_controller_create_and_store_share_schema_gate(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/JournalVoucherController.php'));
        $this->assertSame(2, substr_count($source, 'FinancePostingSchemaGate::class'));
        $this->assertStringContainsString("->mode() === 'typed'", $source);
        $this->assertStringContainsString('->isReady()', $source);
    }
}
