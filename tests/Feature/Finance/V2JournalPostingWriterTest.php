<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\V2JournalPostingWriter;
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

}
