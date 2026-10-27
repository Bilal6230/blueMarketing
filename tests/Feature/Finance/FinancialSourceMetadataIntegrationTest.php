<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\DurableFinancialRequest;
use App\Services\Finance\LedgerSourceAttributionWriter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class FinancialSourceMetadataIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDefaultConnection() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->fail('Financial metadata integration tests require SQLite :memory:.');
        }
        Schema::dropAllTables();
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
        });
        Schema::create('project_head_subheads', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('project_id');
        });
        Schema::create('journal_vouchers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->string('voucher_number');
        });
        Schema::create('commision_vouchers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->string('voucher_number');
        });
        Schema::create('ledgers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('project_head_subheads_id');
            $t->string('type');
            $t->unsignedBigInteger('type_id');
            $t->string('voucher_number');
        });

        $migration = require base_path('database/migrations/2026_10_27_000001_create_financial_source_identity_tables.php');
        $migration->up();

        DB::table('projects')->insert([['id'=>7],['id'=>8]]);
        DB::table('project_head_subheads')->insert([['id'=>3,'project_id'=>7],['id'=>4,'project_id'=>8]]);
        DB::table('journal_vouchers')->insert([['id'=>41,'project_id'=>7,'voucher_number'=>'9001']]);
        DB::table('commision_vouchers')->insert([['id'=>41,'project_id'=>7,'voucher_number'=>'22']]);
        DB::table('ledgers')->insert([['id'=>1,'project_head_subheads_id'=>3,'type'=>'JV','type_id'=>41,'voucher_number'=>'7001']]);
    }

    public function test_migration_provides_both_metadata_tables_without_touching_original_ledger(): void
    {
        $this->assertTrue(Schema::hasTable('ledger_source_attributions'));
        $this->assertTrue(Schema::hasTable('financial_posting_requests'));
        $this->assertSame('7001', DB::table('ledgers')->where('id', 1)->value('voucher_number'));
        $this->assertSame(0, DB::table('ledger_source_attributions')->count());
    }

    public function test_typed_cv_attribution_preserves_original_ledger_and_resolves_correct_display(): void
    {
        app(LedgerSourceAttributionWriter::class)->recordNewPosting(1, 7, 'commission_voucher', 41);
        $ledger = DB::table('ledgers')->first();
        $display = app(\App\Services\Finance\LedgerVoucherDisplayService::class)->display($ledger, 7);
        $this->assertSame('CV-22', $display);
        $this->assertSame('7001', DB::table('ledgers')->where('id', 1)->value('voucher_number'));
        $this->assertSame('new_write_transaction', DB::table('ledger_source_attributions')->value('evidence_kind'));
    }

    public function test_duplicate_attribution_cannot_override_original_typed_owner(): void
    {
        $writer = app(LedgerSourceAttributionWriter::class);
        $writer->recordNewPosting(1, 7, 'commission_voucher', 41);
        $this->expectException(\Illuminate\Database\QueryException::class);
        $writer->recordNewPosting(1, 7, 'journal_voucher', 41);
    }

    public function test_project_mismatch_rejects_attribution_without_any_new_rows(): void
    {
        $this->expectException(\DomainException::class);
        try {
            app(LedgerSourceAttributionWriter::class)->recordNewPosting(1, 8, 'journal_voucher', 41);
        } finally {
            $this->assertSame(0, DB::table('ledger_source_attributions')->count());
        }
    }

    public function test_durable_request_replays_once_without_running_financial_callback_again(): void
    {
        $service = app(DurableFinancialRequest::class);
        $runs = 0;
        $callback = function () use (&$runs): int {
            $runs++;
            return 41;
        };
        $hash = str_repeat('a', 64);
        $first = $service->execute('journal_voucher', 7, 'request_key_123456789', $hash, $callback);
        $second = $service->execute('journal_voucher', 7, 'request_key_123456789', $hash, $callback);
        $this->assertSame(['replayed'=>false,'voucher_id'=>41], $first);
        $this->assertSame(['replayed'=>true,'voucher_id'=>41], $second);
        $this->assertSame(1, $runs);
        $this->assertSame(1, DB::table('financial_posting_requests')->count());
    }

    public function test_conflicting_replay_is_rejected_and_original_request_remains(): void
    {
        $service = app(DurableFinancialRequest::class);
        $service->execute('journal_voucher', 7, 'request_key_123456789', str_repeat('a', 64), fn (): int => 41);
        $this->expectException(\DomainException::class);
        try {
            $service->execute('journal_voucher', 7, 'request_key_123456789', str_repeat('b', 64), fn (): int => 99);
        } finally {
            $this->assertSame(1, DB::table('financial_posting_requests')->count());
            $this->assertSame(41, DB::table('financial_posting_requests')->value('voucher_id'));
        }
    }

    public function test_failed_financial_callback_rolls_back_idempotency_reservation(): void
    {
        $service = app(DurableFinancialRequest::class);
        $this->expectException(\RuntimeException::class);
        try {
            $service->execute('journal_voucher', 7, 'request_key_123456789', str_repeat('a', 64),
                function (): int {
                    DB::table('journal_vouchers')->insert(['id'=>99,'project_id'=>7,'voucher_number'=>'99']);
                    throw new \RuntimeException('Simulated late posting failure');
                });
        } finally {
            $this->assertSame(0, DB::table('financial_posting_requests')->count());
            $this->assertSame(0, DB::table('journal_vouchers')->where('id', 99)->count());
        }
    }

    public function test_request_scope_isolated_by_project_and_type(): void
    {
        $service = app(DurableFinancialRequest::class);
        $hash = str_repeat('a', 64);
        $key = 'request_key_123456789';
        $service->execute('journal_voucher', 7, $key, $hash, fn (): int => 41);
        $service->execute('commission_voucher', 7, $key, $hash, fn (): int => 41);
        $service->execute('journal_voucher', 8, $key, $hash, fn (): int => 99);
        $this->assertSame(3, DB::table('financial_posting_requests')->count());
    }
    public function test_committed_request_with_missing_voucher_refuses_false_replay(): void
    {
        $service = app(DurableFinancialRequest::class);
        $key = 'request_key_123456789';
        $hash = str_repeat('a', 64);
        DB::table('financial_posting_requests')->insert([
            'project_id' => 7, 'source_kind' => 'journal_voucher',
            'request_key' => $key, 'payload_hash' => $hash,
            'status' => 'committed', 'voucher_id' => 999,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $called = false;
        try {
            $service->execute('journal_voucher', 7, $key, $hash, function () use (&$called): int {
                $called = true;
                return 41;
            });
            $this->fail('Orphaned idempotency record must not be treated as a safe replay.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('missing or mismatched voucher', $e->getMessage());
        }
        $this->assertFalse($called);
        $this->assertSame(1, DB::table('financial_posting_requests')->count());
    }

    public function test_committed_request_for_other_project_voucher_refuses_replay(): void
    {
        $service = app(DurableFinancialRequest::class);
        $key = 'request_key_123456789';
        $hash = str_repeat('a', 64);
        DB::table('financial_posting_requests')->insert([
            'project_id' => 8, 'source_kind' => 'journal_voucher',
            'request_key' => $key, 'payload_hash' => $hash,
            'status' => 'committed', 'voucher_id' => 41,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->expectException(\RuntimeException::class);
        $service->execute('journal_voucher', 8, $key, $hash, fn (): int => 99);
    }

}
