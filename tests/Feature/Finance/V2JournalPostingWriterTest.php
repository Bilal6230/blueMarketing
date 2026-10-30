<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\V2JournalPostingWriter;
use App\Services\Finance\V2PostingReversalWriter;
use App\Services\Finance\V2PostingReportReader;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class V2JournalPostingWriterTest extends TestCase
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

        foreach ([
            'projects', 'users', 'commision_vouchers', 'stock_entries',
            'stock_bills', 'customer_ledger', 'booking_vouchers', 'labour_ledgers',
        ] as $name) {
            Schema::create($name, function (Blueprint $table): void { $table->id(); });
        }
        Schema::create('project_head_subheads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
        });
        Schema::create('journal_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('type');
            $table->unsignedBigInteger('voucher_number');
            $table->date('date');
            $table->decimal('total_debit', 15, 2);
            $table->decimal('total_credit', 15, 2);
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('journal_voucher_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('journal_voucher_id');
            $table->unsignedBigInteger('account_id');
            $table->decimal('debit', 15, 2);
            $table->decimal('credit', 15, 2);
            $table->text('description')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        $migration = require base_path('database/v2_migrations/2026_10_30_000100_create_v2_finance_posting_core.php');
        $migration->up();

        DB::table('projects')->insert([['id' => 1], ['id' => 2]]);
        DB::table('project_head_subheads')->insert([
            ['id' => 101, 'project_id' => 1],
            ['id' => 102, 'project_id' => 1],
            ['id' => 201, 'project_id' => 2],
        ]);
        $this->makeJournal(51, 101, 102);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function makeJournal(int $headerId, int $debitAccount, int $creditAccount): void
    {
        DB::table('journal_vouchers')->insert([
            'id' => $headerId, 'project_id' => 1, 'type' => 'JV',
            'voucher_number' => $headerId, 'date' => '2026-10-30',
            'total_debit' => '125.50', 'total_credit' => '125.50',
        ]);
        DB::table('journal_voucher_details')->insert([
            ['id' => $headerId * 10 + 1, 'journal_voucher_id' => $headerId,
             'account_id' => $debitAccount, 'debit' => '125.50', 'credit' => 0,
             'description' => 'Exact debit detail'],
            ['id' => $headerId * 10 + 2, 'journal_voucher_id' => $headerId,
             'account_id' => $creditAccount, 'debit' => 0, 'credit' => '125.50',
             'description' => 'Exact credit detail'],
        ]);
    }

    public function test_jv_posts_exact_detail_links_once_and_replays_safely(): void
    {
        $writer = new V2JournalPostingWriter();
        $id = $writer->post(1, 51, 'JV-TEST-REQUEST-0001');
        $this->assertSame($id, $writer->post(1, 51, 'JV-TEST-REQUEST-0001'));
        $this->assertDatabaseCount('finance_posting_batches', 1);
        $this->assertDatabaseCount('finance_posting_lines', 2);
        $this->assertDatabaseCount('finance_journal_line_sources', 2);
        $this->assertSame('125.5', (string) DB::table('finance_posting_lines')->sum('debit'));
        $this->assertSame('125.5', (string) DB::table('finance_posting_lines')->sum('credit'));
    }

    public function test_wrong_project_account_rolls_back_everything(): void
    {
        DB::table('journal_voucher_details')->where('id', 512)->update(['account_id' => 201]);
        try {
            (new V2JournalPostingWriter())->post(1, 51, 'JV-TEST-REQUEST-0002');
            $this->fail('Expected project mismatch');
        } catch (\DomainException $e) {
            $this->assertSame('V2_JV_ACCOUNT_PROJECT_MISMATCH', $e->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_batches', 0);
        $this->assertDatabaseCount('finance_posting_lines', 0);
    }

    public function test_unbalanced_detail_refuses_posting_without_mutation(): void
    {
        DB::table('journal_voucher_details')->where('id', 512)->update(['credit' => 120]);
        $this->expectException(\DomainException::class);
        try {
            (new V2JournalPostingWriter())->post(1, 51, 'JV-TEST-REQUEST-0003');
        } finally {
            $this->assertDatabaseCount('finance_posting_batches', 0);
        }
    }

    public function test_reused_request_key_for_other_header_is_rejected(): void
    {
        $this->makeJournal(52, 101, 102);
        $writer = new V2JournalPostingWriter();
        $writer->post(1, 51, 'JV-TEST-REQUEST-0004');
        try {
            $writer->post(1, 52, 'JV-TEST-REQUEST-0004');
            $this->fail('Expected idempotency conflict');
        } catch (\DomainException $e) {
            $this->assertSame('V2_IDEMPOTENCY_CONFLICT', $e->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_batches', 1);
    }

    public function test_same_header_with_different_request_key_is_rejected_by_unique_source_fk(): void
    {
        $writer = new V2JournalPostingWriter();
        $writer->post(1, 51, 'JV-TEST-REQUEST-0005');
        try {
            $writer->post(1, 51, 'JV-TEST-REQUEST-0006');
            $this->fail('Expected physical source uniqueness rejection');
        } catch (QueryException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_batches', 1);
        $this->assertDatabaseCount('finance_posting_lines', 2);
    }
    public function test_replay_rejects_tampered_posted_amounts(): void
    {
        $writer = new V2JournalPostingWriter();
        $id = $writer->post(1, 51, 'JV-TEST-REQUEST-0007');
        DB::table('finance_posting_lines')
            ->where('posting_batch_id', $id)
            ->where('project_head_subhead_id', 101)
            ->update(['debit' => '999.99']);
        try {
            $writer->post(1, 51, 'JV-TEST-REQUEST-0007');
            $this->fail('Expected replay integrity rejection');
        } catch (\DomainException $e) {
            $this->assertSame('V2_REPLAY_LEDGER_TAMPERED', $e->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_batches', 1);
    }

    public function test_v2_report_is_typed_date_filtered_and_project_scoped(): void
    {
        (new V2JournalPostingWriter())->post(1, 51, 'JV-REPORT-REQUEST-0001');
        $reader = new V2PostingReportReader();
        $mine = $reader->lines(1, '2026-10-01', '2026-10-31');
        $this->assertCount(2, $mine);
        $this->assertSame('journal', $mine[0]['source_kind']);
        $this->assertSame(51, $mine[0]['source_id']);
        $this->assertSame(51, (int) $mine[0]['voucher_number']);
        $this->assertArrayNotHasKey('type_id', $mine[0]);
        $this->assertSame([], $reader->lines(2));
        $this->assertSame([], $reader->lines(1, '2026-11-01', '2026-11-30'));
    }

    public function test_v2_report_refuses_cross_project_account_ledger_tamper(): void
    {
        (new V2JournalPostingWriter())->post(1, 51, 'JV-REPORT-REQUEST-0002');
        DB::table('finance_posting_lines')->where('project_head_subhead_id', 101)
            ->update(['project_head_subhead_id' => 201]);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REPORT_SOURCE_OR_PROJECT_INTEGRITY_FAILURE');
        (new V2PostingReportReader())->lines(1);
    }

    public function test_v2_report_refuses_invalid_date_interval(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new V2PostingReportReader())->lines(1, '2026-11-01', '2026-10-01');
    }

    public function test_v2_report_rejects_claim_without_its_typed_source_record(): void
    {
        (new V2JournalPostingWriter())->post(1, 51, 'JV-REPORT-REQUEST-0003');
        DB::table('finance_posting_journal_sources')->delete();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REPORT_MISSING_TYPED_SOURCE_LINK');
        (new V2PostingReportReader())->lines(1);
    }

    public function test_jv_replay_rejects_unlinked_extra_posting_line(): void
    {
        $writer = new V2JournalPostingWriter();
        $id = $writer->post(1, 51, 'JV-HARDEN-UNLINKED-001');
        DB::table('finance_posting_lines')->insert([
            'posting_batch_id' => $id, 'project_head_subhead_id' => 101,
            'debit' => '1.00', 'credit' => '0.00',
        ]);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REPLAY_DETAILS_MISSING');
        $writer->post(1, 51, 'JV-HARDEN-UNLINKED-001');
    }

    public function test_jv_replay_refuses_changed_batch_series(): void
    {
        $writer = new V2JournalPostingWriter();
        $id = $writer->post(1, 51, 'JV-HARDEN-SERIES-0001');
        DB::table('finance_posting_batches')->where('id', $id)->update(['voucher_series' => 'SV']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_IDEMPOTENCY_CONFLICT');
        $writer->post(1, 51, 'JV-HARDEN-SERIES-0001');
    }

    public function test_jv_replay_rejects_posted_narrative_tampering(): void
    {
        $writer = new V2JournalPostingWriter();
        $id = $writer->post(1, 51, 'JV-HARDEN-NARRATIVE01');
        DB::table('finance_posting_lines')->where('posting_batch_id', $id)
            ->where('project_head_subhead_id', 101)->update(['description' => 'altered']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REPLAY_LEDGER_TAMPERED');
        $writer->post(1, 51, 'JV-HARDEN-NARRATIVE01');
    }

    public function test_v2_report_rejects_unbalanced_persisted_batch(): void
    {
        (new V2JournalPostingWriter())->post(1, 51, 'JV-HARDEN-REPORT-0001');
        DB::table('finance_posting_lines')->where('project_head_subhead_id', 101)
            ->update(['debit' => '126.50']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REPORT_UNBALANCED_POSTING_BATCH');
        (new V2PostingReportReader())->lines(1);
    }

    public function test_v2_report_rejects_posting_batch_without_lines(): void
    {
        DB::table('finance_posting_batches')->insert([
            'project_id' => 1, 'voucher_series' => 'JV', 'effective_date' => '2026-10-30',
        ]);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REPORT_EMPTY_POSTING_BATCH');
        (new V2PostingReportReader())->lines(1);
    }

    public function test_v2_reversal_is_immutable_balanced_and_reported_with_original_typed_source(): void
    {
        $original = (new V2JournalPostingWriter())->post(1, 51, 'JV-REVERSAL-ORIGINAL-01');
        $reversal = (new V2PostingReversalWriter())->reverse(
            1, $original, 'JV-REVERSAL-REQUEST-01', '2026-10-31', 'Approved correction'
        );
        $this->assertNotSame($original, $reversal);
        $this->assertSame($original, (int) DB::table('finance_posting_batches')
            ->where('id', $reversal)->value('reversal_of_batch_id'));
        $this->assertDatabaseCount('finance_posting_lines', 4);
        $this->assertDatabaseCount('finance_posting_source_claims', 1);
        $this->assertDatabaseCount('finance_posting_journal_sources', 1);
        $this->assertDatabaseCount('finance_journal_line_sources', 2);

        $rows = (new V2PostingReportReader())->lines(1);
        $this->assertCount(4, $rows);
        $opposites = array_values(array_filter($rows, fn ($row) => $row['is_reversal']));
        $this->assertCount(2, $opposites);
        $this->assertSame(51, $opposites[0]['source_id']);
        $this->assertSame('journal', $opposites[0]['source_kind']);
        $this->assertSame($original, (int) $opposites[0]['reversal_of_batch_id']);
        $this->assertCount(2, (new V2PostingReportReader())->lines(1, '2026-10-31', '2026-10-31'));
        $this->assertEqualsWithDelta(0, (float) DB::table('finance_posting_lines')->sum('debit')
            - (float) DB::table('finance_posting_lines')->sum('credit'), 0.001);
        $this->assertSame('125.5', (string) DB::table('finance_posting_lines')
            ->where('posting_batch_id', $original)->sum('debit'));
    }

    public function test_v2_reversal_idempotent_replay_and_different_key_are_protected(): void
    {
        $original = (new V2JournalPostingWriter())->post(1, 51, 'JV-REVERSAL-ORIGINAL-02');
        $writer = new V2PostingReversalWriter();
        $id = $writer->reverse(1, $original, 'JV-REVERSAL-REQUEST-02', '2026-10-31', 'Correction');
        $this->assertSame($id, $writer->reverse(
            1, $original, 'JV-REVERSAL-REQUEST-02', '2026-10-31', 'Correction'
        ));
        try {
            $writer->reverse(1, $original, 'JV-REVERSAL-REQUEST-03', '2026-10-31', 'Correction');
            $this->fail('Second reversal must be rejected');
        } catch (\DomainException $e) {
            $this->assertSame('V2_REVERSAL_ALREADY_EXISTS', $e->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_batches', 2);
    }

    public function test_v2_reversal_refuses_tampering_with_existing_reversal_lines(): void
    {
        $original = (new V2JournalPostingWriter())->post(1, 51, 'JV-REVERSAL-ORIGINAL-04');
        $writer = new V2PostingReversalWriter();
        $reversal = $writer->reverse(1, $original, 'JV-REVERSAL-REQUEST-04', '2026-10-31', 'Correction');
        DB::table('finance_posting_lines')->where('posting_batch_id', $reversal)
            ->where('project_head_subhead_id', 101)->update(['credit' => '126.50']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REVERSAL_REPLAY_TAMPERED');
        $writer->reverse(1, $original, 'JV-REVERSAL-REQUEST-04', '2026-10-31', 'Correction');
    }

    public function test_v2_reversal_refuses_reversal_of_reversal_and_invalid_origin(): void
    {
        $original = (new V2JournalPostingWriter())->post(1, 51, 'JV-REVERSAL-ORIGINAL-05');
        $reversal = (new V2PostingReversalWriter())->reverse(
            1, $original, 'JV-REVERSAL-REQUEST-05', '2026-10-31', 'Correction'
        );
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REVERSAL_ORIGINAL_NOT_POSTED');
        (new V2PostingReversalWriter())->reverse(
            1, $reversal, 'JV-REVERSAL-REQUEST-06', '2026-10-31', 'Correction'
        );
    }

    public function test_v2_reversal_rejects_unbalanced_origin_without_mutation(): void
    {
        $original = (new V2JournalPostingWriter())->post(1, 51, 'JV-REVERSAL-ORIGINAL-07');
        DB::table('finance_posting_lines')->where('posting_batch_id', $original)
            ->where('project_head_subhead_id', 101)->update(['debit' => '126.50']);
        try {
            (new V2PostingReversalWriter())->reverse(
                1, $original, 'JV-REVERSAL-REQUEST-07', '2026-10-31', 'Correction'
            );
            $this->fail('Unbalanced origin was reversed');
        } catch (\DomainException $e) {
            $this->assertSame('V2_REVERSAL_ORIGINAL_UNBALANCED', $e->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_batches', 1);
    }

    public function test_v2_report_rejects_reversal_with_missing_original_source(): void
    {
        $original = (new V2JournalPostingWriter())->post(1, 51, 'JV-REVERSAL-ORIGINAL-08');
        (new V2PostingReversalWriter())->reverse(
            1, $original, 'JV-REVERSAL-REQUEST-08', '2026-10-31', 'Correction'
        );
        DB::table('finance_posting_journal_sources')->where('posting_batch_id', $original)->delete();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REPORT_MISSING_TYPED_SOURCE_LINK');
        (new V2PostingReportReader())->lines(1);
    }

    public function test_v2_report_refuses_balanced_but_not_inverse_reversal(): void
    {
        $original = (new V2JournalPostingWriter())->post(1, 51, 'JV-REVERSAL-ORIGINAL-09');
        $reversal = (new V2PostingReversalWriter())->reverse(
            1, $original, 'JV-REVERSAL-REQUEST-09', '2026-10-31', 'Correction'
        );
        DB::table('finance_posting_lines')->where('posting_batch_id', $reversal)
            ->where('project_head_subhead_id', 101)->update(['credit' => '500.00']);
        DB::table('finance_posting_lines')->where('posting_batch_id', $reversal)
            ->where('project_head_subhead_id', 102)->update(['debit' => '500.00']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REPORT_REVERSAL_NOT_EXACT_INVERSE');
        (new V2PostingReportReader())->lines(1, '2026-10-31', '2026-10-31');
    }

    public function test_v2_reversal_cannot_predate_original_posting(): void
    {
        $original = (new V2JournalPostingWriter())->post(
            1, 51, 'JV-REVERSAL-CHRONOLOGY-01'
        );
        try {
            (new V2PostingReversalWriter())->reverse(
                1, $original, 'JV-REVERSAL-CHRONOLOGY-02', '2026-10-29', 'Correction'
            );
            $this->fail('Backdated reversal was accepted');
        } catch (\DomainException $e) {
            $this->assertSame('V2_REVERSAL_DATE_BEFORE_ORIGINAL', $e->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_batches', 1);
        $this->assertDatabaseCount('finance_posting_lines', 2);
    }

}
