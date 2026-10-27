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
            $t->softDeletes();
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

    public function test_soft_deleted_jv_header_rejects_committed_request_replay(): void
    {
        DB::table('journal_vouchers')->where('id', 41)
            ->update(['deleted_at' => now()]);
        $key = 'request_key_123456789';
        $hash = str_repeat('a', 64);
        DB::table('financial_posting_requests')->insert([
            'project_id' => 7, 'source_kind' => 'journal_voucher',
            'request_key' => $key, 'payload_hash' => $hash,
            'status' => 'committed', 'voucher_id' => 41,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $called = false;
        try {
            app(DurableFinancialRequest::class)->execute('journal_voucher', 7, $key, $hash,
                function () use (&$called): int {
                    $called = true;
                    return 41;
                });
            $this->fail('Soft-deleted voucher must not be replayed as a success.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('missing or mismatched voucher', $e->getMessage());
        }
        $this->assertFalse($called);
    }

    public function test_new_attribution_rejects_wrong_ledger_voucher_id(): void
    {
        DB::table('ledgers')->where('id', 1)->update(['type_id' => 99]);
        try {
            app(LedgerSourceAttributionWriter::class)->recordNewPosting(1, 7, 'journal_voucher', 41);
            $this->fail('Mismatched voucher must not be attributed.');
        } catch (\DomainException $e) {
            $this->assertSame(0, DB::table('ledger_source_attributions')->count());
        }
    }

    public function test_new_attribution_rejects_deleted_journal_header(): void
    {
        DB::table('journal_vouchers')->where('id', 41)->update(['deleted_at' => now()]);
        $this->expectException(\DomainException::class);
        app(LedgerSourceAttributionWriter::class)->recordNewPosting(1, 7, 'journal_voucher', 41);
    }

    public function test_display_ignores_wrong_project_even_with_valid_typed_attribution(): void
    {
        app(LedgerSourceAttributionWriter::class)->recordNewPosting(1, 7, 'journal_voucher', 41);
        DB::table('ledgers')->where('id', 1)->update(['project_head_subheads_id' => 4]);
        $ledger = DB::table('ledgers')->where('id', 1)->first();
        $this->assertSame('JV-7001',
            app(\App\Services\Finance\LedgerVoucherDisplayService::class)->display($ledger, 7));
    }

    public function test_display_ignores_stale_voucher_link_without_relabeling(): void
    {
        app(LedgerSourceAttributionWriter::class)->recordNewPosting(1, 7, 'journal_voucher', 41);
        DB::table('ledgers')->where('id', 1)->update(['type_id' => 99]);
        $ledger = DB::table('ledgers')->where('id', 1)->first();
        $this->assertSame('JV-7001',
            app(\App\Services\Finance\LedgerVoucherDisplayService::class)->display($ledger, 7));
    }

    public function test_provenance_reconciliation_clean_verified_record(): void
    {
        app(LedgerSourceAttributionWriter::class)->recordNewPosting(1,7,'journal_voucher',41);
        $report = app(\App\Services\Finance\FinancialSourceReconciliationInspector::class)->inspect(7);
        $this->assertSame(1,$report['scanned']);
        $this->assertSame([],$report['issues']);
        $this->assertFalse($report['incomplete']);
    }

    public function test_provenance_reconciliation_detects_wrong_ledger_source(): void
    {
        app(LedgerSourceAttributionWriter::class)->recordNewPosting(1,7,'journal_voucher',41);
        DB::table('ledgers')->where('id',1)->update(['type_id'=>99]);
        $report = app(\App\Services\Finance\FinancialSourceReconciliationInspector::class)->inspect(7);
        $this->assertContains('ledger_source_mismatch',$report['issues'][0]['codes']);
        $this->assertSame(99,DB::table('ledgers')->where('id',1)->value('type_id'));
    }

    public function test_provenance_reconciliation_reports_incomplete_bounded_scan(): void
    {
        app(LedgerSourceAttributionWriter::class)->recordNewPosting(1,7,'journal_voucher',41);
        DB::table('ledgers')->insert(['id'=>2,'project_head_subheads_id'=>3,'type'=>'JV','type_id'=>41,'voucher_number'=>'7002']);
        app(LedgerSourceAttributionWriter::class)->recordNewPosting(2,7,'journal_voucher',41);
        $report = app(\App\Services\Finance\FinancialSourceReconciliationInspector::class)->inspect(7,1);
        $this->assertTrue($report['incomplete']);
        $this->assertSame(2,$report['available']);
    }

    public function test_provenance_reconciliation_artisan_fails_on_discrepancy(): void
    {
        app(LedgerSourceAttributionWriter::class)->recordNewPosting(1,7,'journal_voucher',41);
        DB::table('ledgers')->where('id',1)->update(['type_id'=>99]);
        $this->artisan('finance:audit-source-attributions',['project'=>7])
            ->expectsOutputToContain('ledger_source_mismatch')->assertExitCode(1);
        $this->assertSame(1,DB::table('ledger_source_attributions')->count());
    }

    public function test_source_audit_lists_real_project_ids_read_only(): void
    {
        $this->artisan('finance:audit-source-attributions', ['--list-projects' => true])
            ->expectsOutputToContain('"project_ids":[7,8]')->assertExitCode(0);
        $this->assertSame(2, DB::table('projects')->count());
        $this->assertSame(0, DB::table('ledger_source_attributions')->count());
    }

    public function test_source_audit_rejects_unrecognized_project_id(): void
    {
        $this->artisan('finance:audit-source-attributions', ['project' => 999])
            ->expectsOutputToContain('Project does not exist.')->assertExitCode(1);
    }

    public function test_source_audit_rejects_list_mode_with_project_id(): void
    {
        $this->artisan('finance:audit-source-attributions',
            ['project' => 7, '--list-projects' => true])
            ->expectsOutputToContain('Use --list-projects without a project ID.')
            ->assertExitCode(2);
    }

    public function test_readiness_reports_no_unverified_rows_when_typed_jv_attributed(): void
    {
        app(LedgerSourceAttributionWriter::class)->recordNewPosting(1, 7, 'journal_voucher', 41);
        $report = app(\App\Services\Finance\FinancialReadinessInspector::class)->inspect(7);
        $this->assertSame('preliminary_ready', $report['status']);
        $this->assertSame(1, $report['apparently_attributed_ledgers']);
        $this->assertSame(0, $report['unverified_jv_type_ledgers']);
        $this->assertSame([], $report['blockers']);
    }

    public function test_readiness_blocks_unattributed_legacy_without_guessing_jv_ownership(): void
    {
        $report = app(\App\Services\Finance\FinancialReadinessInspector::class)->inspect(7);
        $this->assertSame('blocked', $report['status']);
        $this->assertSame(1, $report['unverified_jv_type_ledgers']);
        $this->assertContains('unverified_legacy_jv_type_ledger_rows', $report['blockers']);
        $this->assertSame(0, DB::table('ledger_source_attributions')->count());
    }

    public function test_readiness_ignores_ledgers_of_other_projects(): void
    {
        $report = app(\App\Services\Finance\FinancialReadinessInspector::class)->inspect(8);
        $this->assertSame(0, $report['candidate_jv_type_ledgers']);
        $this->assertSame('preliminary_ready', $report['status']);
    }

    public function test_readiness_command_returns_failure_for_historical_unknowns(): void
    {
        $this->artisan('finance:audit-readiness', ['project' => 7])
            ->expectsOutputToContain('unverified_legacy_jv_type_ledger_rows')
            ->assertExitCode(1);
        $this->assertSame('7001', DB::table('ledgers')->where('id', 1)->value('voucher_number'));
    }

    public function test_readiness_detects_partial_finance_schema(): void
    {
        Schema::drop('financial_posting_requests');
        $report = app(\App\Services\Finance\FinancialReadinessInspector::class)->inspect(7);
        $this->assertContains('finance_metadata_schema_missing_or_partial', $report['blockers']);
        $this->assertFalse($report['schema']['financial_posting_requests']);
    }

    public function test_finance_metadata_preflight_verifies_columns_and_unique_indexes(): void
    {
        $result = app(\App\Services\Finance\FinanceSchemaPreflight::class)->inspect();
        $this->assertSame('schema_verified', $result['status']);
        $this->assertSame([], $result['blockers']);
        $this->assertTrue($result['unique_indexes']['ledger_source_attributions']);
        $this->assertTrue($result['unique_indexes']['financial_posting_requests']);
    }

    public function test_finance_metadata_preflight_rejects_missing_unique_request_index(): void
    {
        Schema::drop('financial_posting_requests');
        Schema::create('financial_posting_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('source_kind');
            $table->string('request_key');
            $table->char('payload_hash', 64);
            $table->string('status');
            $table->unsignedBigInteger('voucher_id')->nullable();
            $table->timestamps();
        });
        $report = app(\App\Services\Finance\FinanceSchemaPreflight::class)->inspect();
        $this->assertSame('blocked', $report['status']);
        $this->assertContains('financial_request_unique_index_missing', $report['blockers']);
    }

    public function test_finance_metadata_preflight_rejects_missing_column(): void
    {
        Schema::drop('financial_posting_requests');
        Schema::create('financial_posting_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('source_kind');
            $table->string('request_key');
            $table->unique(['project_id', 'source_kind', 'request_key']);
        });
        $report = app(\App\Services\Finance\FinanceSchemaPreflight::class)->inspect();
        $this->assertContains('financial_posting_requests_incomplete_columns', $report['blockers']);
    }

    public function test_finance_metadata_preflight_artisan_is_read_only(): void
    {
        $this->artisan('finance:metadata-preflight')
            ->expectsOutputToContain('schema_verified')->assertExitCode(0);
        $this->assertSame(0, DB::table('financial_posting_requests')->count());
    }

    public function test_rollout_assessment_reports_all_projects_and_legacy_gaps(): void
    {
        $report = app(\App\Services\Finance\FinanceRolloutAssessment::class)->inspect();
        $this->assertSame(2, $report['scanned_projects']);
        $this->assertSame('blocked', $report['status']);
        $this->assertSame(1, $report['unverified_jv_type_ledgers']);
        $this->assertSame(7, $report['projects'][0]['project_id']);
        $this->assertSame(8, $report['projects'][1]['project_id']);
    }

    public function test_rollout_assessment_succeeds_only_for_fully_attributed_fixture(): void
    {
        app(LedgerSourceAttributionWriter::class)->recordNewPosting(1, 7, 'journal_voucher', 41);
        $report = app(\App\Services\Finance\FinanceRolloutAssessment::class)->inspect();
        $this->assertSame('preliminary_ready', $report['status']);
        $this->assertSame(0, $report['blocked_projects']);
        $this->assertSame(0, $report['unverified_jv_type_ledgers']);
    }

    public function test_rollout_assessment_detects_incomplete_project_inventory(): void
    {
        $report = app(\App\Services\Finance\FinanceRolloutAssessment::class)->inspect(1);
        $this->assertTrue($report['incomplete']);
        $this->assertSame('blocked', $report['status']);
        $this->assertSame(1, $report['scanned_projects']);
    }

    public function test_rollout_command_is_read_only_and_exits_nonzero_on_blockers(): void
    {
        $this->artisan('finance:rollout-assessment', ['--limit' => 100])
            ->expectsOutputToContain('"unverified_jv_type_ledgers":1')
            ->assertExitCode(1);
        $this->assertSame(0, DB::table('ledger_source_attributions')->count());
        $this->assertSame('7001', DB::table('ledgers')->where('id', 1)->value('voucher_number'));
    }

    public function test_rollout_command_rejects_invalid_scan_limit(): void
    {
        $this->artisan('finance:rollout-assessment', ['--limit' => 0])
            ->expectsOutputToContain('Project limit must be between 1 and 1000.')
            ->assertExitCode(2);
    }

    public function test_legacy_case_inventory_never_guesses_owner_from_colliding_headers(): void
    {
        $report = app(\App\Services\Finance\LegacyVoucherCollisionInventory::class)->inspect(7);
        $this->assertSame(1, $report['available']);
        $this->assertSame('both_headers', $report['cases'][0]['candidate_category']);
        $this->assertFalse($report['cases'][0]['source_verified']);
        $this->assertSame('7001', $report['cases'][0]['original_voucher_number']);
    }

    public function test_legacy_case_inventory_excludes_explicitly_attributed_rows(): void
    {
        app(LedgerSourceAttributionWriter::class)->recordNewPosting(1, 7, 'journal_voucher', 41);
        $report = app(\App\Services\Finance\LegacyVoucherCollisionInventory::class)->inspect(7);
        $this->assertSame(0, $report['available']);
        $this->assertSame([], $report['cases']);
    }

    public function test_legacy_case_inventory_bounded_scan_cannot_claim_completeness(): void
    {
        DB::table('ledgers')->insert(['id'=>2,'project_head_subheads_id'=>3,'type'=>'JV',
            'type_id'=>999,'voucher_number'=>'7002']);
        $report = app(\App\Services\Finance\LegacyVoucherCollisionInventory::class)->inspect(7, 1);
        $this->assertTrue($report['incomplete']);
        $this->assertSame(2, $report['available']);
        $this->assertSame(1, $report['scanned']);
    }

    public function test_legacy_case_artisan_is_read_only_and_nonzero_on_review_needed(): void
    {
        $this->artisan('finance:audit-legacy-cases', ['project'=>7, '--limit'=>100])
            ->expectsOutputToContain('"candidate_category":"both_headers"')->assertExitCode(1);
        $this->assertSame('7001', DB::table('ledgers')->where('id',1)->value('voucher_number'));
        $this->assertSame(0, DB::table('ledger_source_attributions')->count());
    }

    public function test_legacy_summary_classifies_complete_project_without_inventing_provenance(): void
    {
        DB::table('ledgers')->insert(['id'=>2,'project_head_subheads_id'=>3,'type'=>'JV',
            'type_id'=>999,'voucher_number'=>'7002']);
        $report = app(\App\Services\Finance\LegacyVoucherCollisionInventory::class)->summarize(7);
        $this->assertSame(2, $report['scanned']);
        $this->assertFalse($report['incomplete']);
        $this->assertSame(1, $report['categories_in_scanned_rows']['both_headers']);
        $this->assertSame(1, $report['categories_in_scanned_rows']['neither_header']);
        $this->assertFalse($report['source_verified']);
    }

    public function test_legacy_summary_respects_cap_and_never_claims_full_scan(): void
    {
        DB::table('ledgers')->insert(['id'=>2,'project_head_subheads_id'=>3,'type'=>'JV',
            'type_id'=>999,'voucher_number'=>'7002']);
        $report = app(\App\Services\Finance\LegacyVoucherCollisionInventory::class)->summarize(7, 1);
        $this->assertSame(2, $report['available']);
        $this->assertTrue($report['incomplete']);
        $this->assertSame(1, $report['scanned']);
    }

    public function test_legacy_summary_command_uses_compact_read_only_report(): void
    {
        $this->artisan('finance:audit-legacy-cases',
            ['project'=>7, '--summary'=>true, '--max-rows'=>5000])
            ->expectsOutputToContain('"source_verified":false')->assertExitCode(1);
        $this->assertSame(0, DB::table('ledger_source_attributions')->count());
        $this->assertSame('7001', DB::table('ledgers')->where('id',1)->value('voucher_number'));
    }

    public function test_legacy_summary_command_rejects_invalid_cap(): void
    {
        $this->artisan('finance:audit-legacy-cases',
            ['project'=>7, '--summary'=>true, '--max-rows'=>0])
            ->expectsOutputToContain('Summary --max-rows must be between 1 and 10000.')
            ->assertExitCode(2);
    }

    public function test_review_queue_finds_unmatched_case_past_earlier_matched_rows(): void
    {
        DB::table('ledgers')->insert(['id'=>2,'project_head_subheads_id'=>3,
            'type'=>'JV','type_id'=>999,'voucher_number'=>'7002']);
        $review = app(\App\Services\Finance\LegacyVoucherCollisionInventory::class)
            ->reviewQueue(7, 'neither_header', 1);
        $this->assertSame(1, $review['available']);
        $this->assertSame(2, $review['cases'][0]['ledger_id']);
        $this->assertSame('7002', $review['cases'][0]['original_voucher_number']);
        $this->assertFalse($review['cases'][0]['source_verified']);
    }

    public function test_review_queue_only_returns_requested_collision_category(): void
    {
        $review = app(\App\Services\Finance\LegacyVoucherCollisionInventory::class)
            ->reviewQueue(7, 'both_headers', 100);
        $this->assertSame(1, $review['available']);
        $this->assertSame(1, $review['cases'][0]['ledger_id']);
        $this->assertFalse($review['incomplete']);
    }

    public function test_review_queue_omits_records_with_explicit_attribution(): void
    {
        app(LedgerSourceAttributionWriter::class)->recordNewPosting(1, 7, 'journal_voucher', 41);
        $review = app(\App\Services\Finance\LegacyVoucherCollisionInventory::class)
            ->reviewQueue(7, 'both_headers', 100);
        $this->assertSame(0, $review['available']);
        $this->assertSame([], $review['cases']);
    }

    public function test_review_queue_command_exports_focused_cases_read_only(): void
    {
        DB::table('ledgers')->insert(['id'=>2,'project_head_subheads_id'=>3,
            'type'=>'JV','type_id'=>999,'voucher_number'=>'7002']);
        $this->artisan('finance:audit-legacy-cases',
            ['project'=>7, '--review-category'=>'neither_header', '--limit'=>100])
            ->expectsOutputToContain('"ledger_id":2')->assertExitCode(1);
        $this->assertSame(0, DB::table('ledger_source_attributions')->count());
        $this->assertSame('7002', DB::table('ledgers')->where('id',2)->value('voucher_number'));
    }

    public function test_evidence_packet_preserves_colliding_jv_cv_candidates_without_certifying(): void
    {
        $packet = app(\App\Services\Finance\LegacyVoucherEvidencePacket::class)->inspect(7,1);
        $this->assertSame('both_headers', $packet['candidate_category']);
        $this->assertSame('7001', $packet['ledger']['original_voucher_number']);
        $this->assertSame('9001', $packet['candidate_jv_header']['voucher_number']);
        $this->assertSame('22', $packet['candidate_cv_header']['voucher_number']);
        $this->assertFalse($packet['source_verified']);
    }

    public function test_evidence_packet_refuses_other_project_ledger(): void
    {
        $this->expectException(\DomainException::class);
        app(\App\Services\Finance\LegacyVoucherEvidencePacket::class)->inspect(8,1);
    }

    public function test_evidence_packet_reports_neither_header_without_guessing(): void
    {
        DB::table('ledgers')->where('id',1)->update(['type_id'=>999]);
        $packet = app(\App\Services\Finance\LegacyVoucherEvidencePacket::class)->inspect(7,1);
        $this->assertSame('neither_header',$packet['candidate_category']);
        $this->assertNull($packet['candidate_jv_header']);
        $this->assertNull($packet['candidate_cv_header']);
        $this->assertFalse($packet['source_verified']);
    }

    public function test_evidence_packet_cli_is_read_only(): void
    {
        $this->artisan('finance:audit-legacy-evidence',['project'=>7,'ledger'=>1])
            ->expectsOutputToContain('"requires_external_evidence"')->assertExitCode(0);
        $this->assertSame('7001',DB::table('ledgers')->where('id',1)->value('voucher_number'));
        $this->assertSame(0,DB::table('ledger_source_attributions')->count());
    }

    public function test_evidence_batch_keeps_all_packets_unverified(): void
    {
        DB::table('ledgers')->insert(['id'=>2,'project_head_subheads_id'=>3,'type'=>'JV',
            'type_id'=>999,'voucher_number'=>'7002']);
        $result = app(\App\Services\Finance\LegacyVoucherEvidencePacket::class)->inspectBatch(7,[1,2]);
        $this->assertSame(2,$result['scanned']);
        $this->assertSame(1,$result['candidate_categories']['both_headers']);
        $this->assertSame(1,$result['candidate_categories']['neither_header']);
        $this->assertFalse($result['source_verified']);
    }

    public function test_evidence_batch_refuses_duplicate_ledger_ids(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(\App\Services\Finance\LegacyVoucherEvidencePacket::class)->inspectBatch(7,[1,1]);
    }

    public function test_evidence_batch_denies_mixed_project_records(): void
    {
        DB::table('ledgers')->insert(['id'=>2,'project_head_subheads_id'=>4,'type'=>'JV',
            'type_id'=>99,'voucher_number'=>'7002']);
        $this->expectException(\DomainException::class);
        app(\App\Services\Finance\LegacyVoucherEvidencePacket::class)->inspectBatch(7,[1,2]);
    }

    public function test_evidence_batch_command_outputs_read_only_packets(): void
    {
        $this->artisan('finance:audit-legacy-evidence-batch',
            ['project'=>7,'ledgers'=>'1'])
            ->expectsOutputToContain('"scanned":1')->assertExitCode(0);
        $this->assertSame(0,DB::table('ledger_source_attributions')->count());
    }

    private function conflictReviewFixture(int $sourceId = 41): array
    {
        $packet = app(\App\Services\Finance\LegacyVoucherEvidencePacket::class)->inspectBatch(7,[1]);
        $worksheet = app(\App\Services\Finance\LegacyEvidenceReviewWorksheet::class);
        $review = $worksheet->template($packet);
        $review['rows'][0] = array_merge($review['rows'][0],[
            'proposed_source_kind'=>'journal_voucher','proposed_header_id'=>$sourceId,
            'evidence_reference'=>'independent-archive','reviewer'=>'Auditor',
            'reviewed_at'=>'2026-10-27','review_note'=>'External evidence separately verified',
        ]);
        $checkpoint = app(\App\Services\Finance\LegacyEvidenceApprovalCheckpoint::class);
        $approval = $checkpoint->manifest($packet,$review,$worksheet);
        $approval['approvals'][0]=['role'=>'finance_reviewer','name'=>'Lead','reviewed_at'=>'2026-10-27','evidence_reference'=>'F-1'];
        $approval['approvals'][1]=['role'=>'independent_approver','name'=>'Controller','reviewed_at'=>'2026-10-27','evidence_reference'=>'A-2'];
        return [$packet,$review,$approval];
    }

    public function test_conflict_gate_blocks_existing_typed_attribution_without_mutating(): void
    {
        [$batch,$review,$approval]=$this->conflictReviewFixture();
        app(LedgerSourceAttributionWriter::class)->recordNewPosting(1,7,'journal_voucher',41);
        $result=app(\App\Services\Finance\LegacyEvidenceConflictGate::class)->inspect(
            $batch,$review,$approval,app(\App\Services\Finance\LegacyEvidenceReviewWorksheet::class),
            app(\App\Services\Finance\LegacyEvidenceApprovalCheckpoint::class));
        $this->assertSame('blocked',$result['status']);
        $this->assertContains('ledger_already_attributed',array_column($result['issues'],'code'));
        $this->assertSame(1,DB::table('ledger_source_attributions')->count());
    }

    public function test_conflict_gate_never_certifies_unresolved_review(): void
    {
        [$batch,$review,$approval]=$this->conflictReviewFixture();
        $review['rows'][0]['proposed_source_kind']='unresolved';
        $review['rows'][0]['proposed_header_id']=null;
        $worksheet=app(\App\Services\Finance\LegacyEvidenceReviewWorksheet::class);
        $checkpoint=app(\App\Services\Finance\LegacyEvidenceApprovalCheckpoint::class);
        $approval=$checkpoint->manifest($batch,$review,$worksheet);
        $approval['approvals'][0]=['role'=>'finance_reviewer','name'=>'Lead','reviewed_at'=>'2026-10-27','evidence_reference'=>'F-1'];
        $approval['approvals'][1]=['role'=>'independent_approver','name'=>'Controller','reviewed_at'=>'2026-10-27','evidence_reference'=>'A-2'];
        $result=app(\App\Services\Finance\LegacyEvidenceConflictGate::class)->inspect($batch,$review,$approval,$worksheet,$checkpoint);
        $this->assertContains('ownership_unresolved',array_column($result['issues'],'code'));
        $this->assertFalse($result['source_verified']);
    }

    public function test_conflict_gate_succeeds_only_as_external_reconciliation_candidate(): void
    {
        [$batch,$review,$approval]=$this->conflictReviewFixture();
        $result=app(\App\Services\Finance\LegacyEvidenceConflictGate::class)->inspect(
            $batch,$review,$approval,app(\App\Services\Finance\LegacyEvidenceReviewWorksheet::class),
            app(\App\Services\Finance\LegacyEvidenceApprovalCheckpoint::class));
        $this->assertSame('eligible_for_external_reconciliation',$result['status']);
        $this->assertFalse($result['source_verified']);
        $this->assertSame(0,DB::table('ledger_source_attributions')->count());
    }

    public function test_conflict_gate_fails_closed_when_schema_absent(): void
    {
        [$batch,$review,$approval]=$this->conflictReviewFixture();
        Schema::drop('ledger_source_attributions');
        $result=app(\App\Services\Finance\LegacyEvidenceConflictGate::class)->inspect(
            $batch,$review,$approval,app(\App\Services\Finance\LegacyEvidenceReviewWorksheet::class),
            app(\App\Services\Finance\LegacyEvidenceApprovalCheckpoint::class));
        $this->assertContains('attribution_schema_missing',array_column($result['issues'],'code'));
    }
}
