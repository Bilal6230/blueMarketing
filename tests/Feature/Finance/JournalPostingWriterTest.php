<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\JournalPostingWriter;
use App\Services\Finance\PostingReversalWriter;
use App\Services\Finance\PostingReportReader;
use App\Services\Finance\PostingGateway;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JournalPostingWriterTest extends TestCase
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
        Schema::create('head_accountings',function(Blueprint $table): void {
            $table->id();$table->boolean('is_active')->default(true);
        });
        Schema::create('subhead_accountings',function(Blueprint $table): void {
            $table->id();$table->boolean('is_active')->default(true);
        });
        Schema::create('project_head_subheads', function (Blueprint $table): void {
            $table->id();$table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('head_accounting_id');
            $table->unsignedBigInteger('subhead_accounting_id');
        });
        Schema::create('journal_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('type');
            $table->unsignedBigInteger('voucher_number');
            $table->date('date');
            $table->decimal('total_debit', 15, 2);
            $table->decimal('total_credit', 15, 2);
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('journal_voucher_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('journal_voucher_id');
            $table->unsignedBigInteger('account_id');
            $table->decimal('debit', 15, 2);
            $table->decimal('credit', 15, 2);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
        $migration = require base_path('database/finance_legacy_migrations/2026_10_30_000100_create_finance_finance_posting_core.php');
        $migration->up();
        if (!Schema::hasColumn('finance_posting_lines','is_active')) {
            Schema::table('finance_posting_lines',function(Blueprint $table) {
                $table->boolean('is_active')->default(true);
            });
        }

        DB::table('projects')->insert([['id' => 1], ['id' => 2]]);
        DB::table('head_accountings')->insert([['id'=>1],['id'=>2]]);
        DB::table('subhead_accountings')->insert([['id'=>1],['id'=>2]]);
        DB::table('project_head_subheads')->insert([
            ['id'=>101,'project_id'=>1,'head_accounting_id'=>1,'subhead_accounting_id'=>1],
            ['id'=>102,'project_id'=>1,'head_accounting_id'=>1,'subhead_accounting_id'=>2],
            ['id'=>201,'project_id'=>2,'head_accounting_id'=>2,'subhead_accounting_id'=>1],
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

    public function test_posted_jv_full_correction_preserves_one_batch_and_exact_unchanged_detail(): void
    {
        $key='JV-FULL-CORRECTION-0001';
        $batch=(new PostingGateway())->postJournal(1,51,$key);
        $editor=new \App\Services\Finance\JournalApprovedVoucherEditor();
        $before=DB::table('finance_journal_line_sources as src')
            ->join('finance_posting_lines as line','line.id','=','src.posting_line_id')
            ->where('line.posting_batch_id',$batch)
            ->where('src.journal_voucher_detail_id',511)->value('line.id');
        $editor->update(1,51,'2026-10-30',[
            ['detail_id'=>511,'account_id'=>101,'debit'=>'125.50','credit'=>'0.00',
                'description'=>'Exact debit detail'],
            ['detail_id'=>512,'account_id'=>102,'debit'=>'0.00','credit'=>'150.50',
                'description'=>'Changed credit'],
            ['detail_id'=>null,'account_id'=>101,'debit'=>'25.00','credit'=>'0.00',
                'description'=>'Additional debit'],
        ],[],null);
        self::assertSame(1,DB::table('finance_posting_batches')->count());
        self::assertSame(3,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->where('is_active',1)->count());
        self::assertSame(4,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->count());
        self::assertSame((int)$before,(int)DB::table('finance_journal_line_sources as src')
            ->join('finance_posting_lines as line','line.id','=','src.posting_line_id')
            ->where('src.journal_voucher_detail_id',511)
            ->where('line.is_active',1)->value('line.id'));
        self::assertSame(150.5,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->where('is_active',1)->sum('debit'));
        self::assertSame(150.5,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->where('is_active',1)->sum('credit'));
        self::assertSame($batch,(new PostingGateway())->postJournal(1,51,$key));
        $oldCount=DB::table('finance_posting_lines')->count();
        $active=DB::table('journal_voucher_details')->where('journal_voucher_id',51)
            ->whereNull('deleted_at')->orderBy('id')->get();
        $rows=$active->map(fn($row)=>[
            'detail_id'=>(int)$row->id,'account_id'=>(int)$row->account_id,
            'debit'=>(string)$row->debit,'credit'=>(string)$row->credit,
            'description'=>$row->description,
        ])->all();
        $editor->update(1,51,'2026-10-30',$rows,[],null);
        self::assertSame($oldCount,DB::table('finance_posting_lines')->count());
    }

    public function test_posted_jv_correction_rejects_foreign_detail_and_preserves_source(): void
    {
        $key='JV-FOREIGN-DETAIL-CORRECT-0001';
        $batch=(new PostingGateway())->postJournal(1,51,$key);
        $editor=new \App\Services\Finance\JournalApprovedVoucherEditor();
        $before=DB::table('finance_posting_lines')->where('posting_batch_id',$batch)->count();
        try {
            $editor->update(1,51,'2026-10-30',[
                ['detail_id'=>99999,'account_id'=>101,'debit'=>'125.50','credit'=>'0',
                    'description'=>'Invalid'],
                ['detail_id'=>512,'account_id'=>102,'debit'=>'0','credit'=>'125.50',
                    'description'=>'Exact credit detail'],
            ],[],null);
            self::fail('Foreign source detail ID must never be attached to a posted JV.');
        }catch(\DomainException $error) {
            self::assertStringContainsString('another voucher',$error->getMessage());
        }
        self::assertSame($before,DB::table('finance_posting_lines')->where('posting_batch_id',$batch)->count());
        self::assertSame(2,DB::table('journal_voucher_details')
            ->where('journal_voucher_id',51)->whereNull('deleted_at')->count());
    }

    public function test_posted_journal_ordinary_delete_archives_one_batch_without_reversal(): void
    {
        $batch=(new PostingGateway())->postJournal(1,51,'JV-POSTED-DELETE-00001');
        (new \App\Services\Finance\PostedVoucherArchiver())->archive(1,'journal',51);
        self::assertSame(1,DB::table('finance_posting_batches')->count());
        self::assertSame(2,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->count());
        self::assertSame(0,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->where('is_active',1)->count());
        self::assertNotNull(DB::table('journal_vouchers')->where('id',51)->value('deleted_at'));
        self::assertSame(0,DB::table('journal_voucher_details')
            ->where('journal_voucher_id',51)->whereNull('deleted_at')->count());
        self::assertSame([],(new PostingReportReader())->lines(1));
        self::assertSame([],(new \App\Services\Finance\PostingBatchIntegrityAudit())->audit(1));
        try {
            (new \App\Services\Finance\PostedVoucherArchiver())->archive(1,'journal',51);
            self::fail('Archived JV must not be deleted twice.');
        }catch(\DomainException $e) {
            self::assertStringContainsString('currently posted',$e->getMessage());
        }
        $retired=DB::table('finance_posting_lines')->where('posting_batch_id',$batch)
            ->orderBy('id')->value('id');
        DB::table('finance_posting_lines')->where('id',$retired)->update(['is_active'=>1]);
        $issues=(new \App\Services\Finance\PostingBatchIntegrityAudit())->audit(1);
        self::assertNotEmpty($issues);
        self::assertContains('ARCHIVED_VOUCHER_WITH_ACTIVE_POSTINGS',$issues[0]['issues']);
    }

    public function test_isolated_jv_draft_updates_before_posting_and_locks_after(): void
    {
        $draft = new \App\Services\Finance\JournalDraftWorkflow();
        $lines = [
            ['account_id' => 101, 'debit' => '80.00', 'credit' => '0.00'],
            ['account_id' => 102, 'debit' => '0.00', 'credit' => '80.00'],
        ];
        $draft->updateUnposted(1, 51, '2026-10-31', $lines);
        $this->assertDatabaseHas('journal_vouchers', [
            'id' => 51, 'date' => '2026-10-31', 'total_debit' => '80.00',
        ]);
        $this->assertSame(2, DB::table('journal_voucher_details')
            ->where('journal_voucher_id', 51)->whereNull('deleted_at')->count());
        $this->assertSame(2, DB::table('journal_voucher_details')
            ->where('journal_voucher_id', 51)->whereNotNull('deleted_at')->count());
        (new PostingGateway())->postJournal(1, 51, 'JV-DRAFT-POST-LOCK-0001');
        try {
            $draft->updateUnposted(1, 51, '2026-10-31', $lines);
            $this->fail('Posted JV must never accept direct line edits');
        } catch (\DomainException $exception) {
            $this->assertDatabaseCount('finance_posting_batches', 1);
        }
    }

    public function test_isolated_jv_draft_refuses_foreign_project_without_changes(): void
    {
        $draft = new \App\Services\Finance\JournalDraftWorkflow();
        try {
            $draft->updateUnposted(2, 51, '2026-10-31', [
                ['account_id' => 201, 'debit' => '30.00', 'credit' => '0.00'],
                ['account_id' => 201, 'debit' => '0.00', 'credit' => '30.00'],
            ]);
            $this->fail('Foreign project JV update must fail');
        } catch (\DomainException $exception) {
            $this->assertDatabaseHas('journal_vouchers', [
                'id' => 51, 'date' => '2026-10-30', 'total_debit' => '125.50',
            ]);
        }
    }

    public function test_draft_refuses_exact_ledger_detail_link_with_mismatched_legacy_identity(): void
    {
        Schema::create('ledgers', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->unsignedBigInteger('type_id');
            $table->unsignedBigInteger('journal_voucher_detail_id')->nullable();
        });
        DB::table('ledgers')->insert([
            'type' => 'SV', 'type_id' => 999, 'journal_voucher_detail_id' => 511,
        ]);
        $before = DB::table('journal_voucher_details')->orderBy('id')->get()->toJson();
        try {
            (new \App\Services\Finance\JournalDraftWorkflow())->updateUnposted(
                1, 51, '2026-10-31', [
                    ['account_id' => 101, 'debit' => '80.00', 'credit' => '0.00'],
                    ['account_id' => 102, 'debit' => '0.00', 'credit' => '80.00'],
                ]
            );
            $this->fail('Exact linked detail must remain immutable');
        } catch (\DomainException $exception) {
            $this->assertSame('V2_JV_DRAFT_IMMUTABLE', $exception->getMessage());
        }
        $this->assertSame($before, DB::table('journal_voucher_details')->orderBy('id')->get()->toJson());
        $this->assertDatabaseHas('journal_vouchers', ['id' => 51, 'date' => '2026-10-30']);
        $this->assertDatabaseHas('ledgers', ['journal_voucher_detail_id' => 511]);
    }

    public function test_draft_preserves_unrelated_ledger_detail_links(): void
    {
        $this->makeJournal(52, 101, 102);
        Schema::create('ledgers', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->unsignedBigInteger('type_id');
            $table->unsignedBigInteger('journal_voucher_detail_id')->nullable();
        });
        DB::table('ledgers')->insert([
            'type' => 'JV', 'type_id' => 52, 'journal_voucher_detail_id' => 521,
        ]);
        (new \App\Services\Finance\JournalDraftWorkflow())->updateUnposted(
            1, 51, '2026-10-31', [
                ['account_id' => 101, 'debit' => '80.00', 'credit' => '0.00'],
                ['account_id' => 102, 'debit' => '0.00', 'credit' => '80.00'],
            ]
        );
        $this->assertDatabaseHas('journal_vouchers', ['id' => 51, 'total_debit' => '80.00']);
        $this->assertDatabaseHas('journal_voucher_details', ['id' => 521, 'debit' => '125.50']);
        $this->assertDatabaseHas('ledgers', ['journal_voucher_detail_id' => 521]);
    }

    public function test_jv_posts_exact_detail_links_once_and_replays_safely(): void
    {
        $writer = new JournalPostingWriter();
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
            (new JournalPostingWriter())->post(1, 51, 'JV-TEST-REQUEST-0002');
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
            (new JournalPostingWriter())->post(1, 51, 'JV-TEST-REQUEST-0003');
        } finally {
            $this->assertDatabaseCount('finance_posting_batches', 0);
        }
    }

    public function test_reused_request_key_for_other_header_is_rejected(): void
    {
        $this->makeJournal(52, 101, 102);
        $writer = new JournalPostingWriter();
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
        $writer = new JournalPostingWriter();
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
        $writer = new JournalPostingWriter();
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
        (new JournalPostingWriter())->post(1, 51, 'JV-REPORT-REQUEST-0001');
        $reader = new PostingReportReader();
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
        (new JournalPostingWriter())->post(1, 51, 'JV-REPORT-REQUEST-0002');
        DB::table('finance_posting_lines')->where('project_head_subhead_id', 101)
            ->update(['project_head_subhead_id' => 201]);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REPORT_SOURCE_OR_PROJECT_INTEGRITY_FAILURE');
        (new PostingReportReader())->lines(1);
    }

    public function test_v2_report_refuses_invalid_date_interval(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PostingReportReader())->lines(1, '2026-11-01', '2026-10-01');
    }

    public function test_v2_report_rejects_claim_without_its_typed_source_record(): void
    {
        (new JournalPostingWriter())->post(1, 51, 'JV-REPORT-REQUEST-0003');
        DB::table('finance_posting_journal_sources')->delete();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REPORT_MISSING_TYPED_SOURCE_LINK');
        (new PostingReportReader())->lines(1);
    }

    public function test_jv_replay_rejects_unlinked_extra_posting_line(): void
    {
        $writer = new JournalPostingWriter();
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
        $writer = new JournalPostingWriter();
        $id = $writer->post(1, 51, 'JV-HARDEN-SERIES-0001');
        DB::table('finance_posting_batches')->where('id', $id)->update(['voucher_series' => 'SV']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_IDEMPOTENCY_CONFLICT');
        $writer->post(1, 51, 'JV-HARDEN-SERIES-0001');
    }

    public function test_jv_replay_rejects_posted_narrative_tampering(): void
    {
        $writer = new JournalPostingWriter();
        $id = $writer->post(1, 51, 'JV-HARDEN-NARRATIVE01');
        DB::table('finance_posting_lines')->where('posting_batch_id', $id)
            ->where('project_head_subhead_id', 101)->update(['description' => 'altered']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REPLAY_LEDGER_TAMPERED');
        $writer->post(1, 51, 'JV-HARDEN-NARRATIVE01');
    }

    public function test_v2_report_rejects_unbalanced_persisted_batch(): void
    {
        (new JournalPostingWriter())->post(1, 51, 'JV-HARDEN-REPORT-0001');
        DB::table('finance_posting_lines')->where('project_head_subhead_id', 101)
            ->update(['debit' => '126.50']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REPORT_UNBALANCED_POSTING_BATCH');
        (new PostingReportReader())->lines(1);
    }

    public function test_v2_report_rejects_posting_batch_without_lines(): void
    {
        DB::table('finance_posting_batches')->insert([
            'project_id' => 1, 'voucher_series' => 'JV', 'effective_date' => '2026-10-30',
        ]);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REPORT_EMPTY_POSTING_BATCH');
        (new PostingReportReader())->lines(1);
    }

    public function test_milestone05_reversal_switch_blocks_then_allows_same_verified_journal_batch(): void
    {
        Schema::create('daily_voucher_approval_policies',function(Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('voucher_type',12);
            $table->boolean('approval_required')->default(false);
            $table->boolean('reversal_enabled')->default(true);
            $table->unique(['project_id','voucher_type']);
        });
        DB::table('daily_voucher_approval_policies')->insert([
            'project_id'=>1,'voucher_type'=>'JV','reversal_enabled'=>false,
        ]);
        $original=(new JournalPostingWriter())->post(1,51,'JV-M05-ORIGINAL-20261124');
        $writer=new PostingReversalWriter();
        $beforeBatches=DB::table('finance_posting_batches')->count();
        $beforeLines=DB::table('finance_posting_lines')->count();
        try{
            $writer->reverse(1,$original,'JV-M05-REVERSE-20261124','2026-10-31','Test reversal');
            $this->fail('Disabled reversal must never create a posting.');
        }catch(\DomainException $exception){
            $this->assertSame('VOUCHER_REVERSAL_DISABLED',$exception->getMessage());
        }
        $this->assertSame($beforeBatches,DB::table('finance_posting_batches')->count());
        $this->assertSame($beforeLines,DB::table('finance_posting_lines')->count());
        DB::table('daily_voucher_approval_policies')
            ->where('project_id',1)->where('voucher_type','JV')
            ->update(['reversal_enabled'=>true]);
        $reversal=$writer->reverse(1,$original,'JV-M05-REVERSE-20261124','2026-10-31','Test reversal');
        $this->assertNotSame($original,$reversal);
        $this->assertSame($original,(int)DB::table('finance_posting_batches')
            ->where('id',$reversal)->value('reversal_of_batch_id'));
        $this->assertSame($beforeBatches+1,DB::table('finance_posting_batches')->count());
        $this->assertSame($beforeLines+2,DB::table('finance_posting_lines')->count());
        $this->assertDatabaseCount('finance_posting_source_claims',1);
    }

    public function test_v2_reversal_is_immutable_balanced_and_reported_with_original_typed_source(): void
    {
        $original = (new JournalPostingWriter())->post(1, 51, 'JV-REVERSAL-ORIGINAL-01');
        $reversal = (new PostingReversalWriter())->reverse(
            1, $original, 'JV-REVERSAL-REQUEST-01', '2026-10-31', 'Approved correction'
        );
        $this->assertNotSame($original, $reversal);
        $this->assertSame($original, (int) DB::table('finance_posting_batches')
            ->where('id', $reversal)->value('reversal_of_batch_id'));
        $this->assertDatabaseCount('finance_posting_lines', 4);
        $this->assertDatabaseCount('finance_posting_source_claims', 1);
        $this->assertDatabaseCount('finance_posting_journal_sources', 1);
        $this->assertDatabaseCount('finance_journal_line_sources', 2);

        $rows = (new PostingReportReader())->lines(1);
        $this->assertCount(4, $rows);
        $opposites = array_values(array_filter($rows, fn ($row) => $row['is_reversal']));
        $this->assertCount(2, $opposites);
        $this->assertSame(51, $opposites[0]['source_id']);
        $this->assertSame('journal', $opposites[0]['source_kind']);
        $this->assertSame($original, (int) $opposites[0]['reversal_of_batch_id']);
        $this->assertCount(2, (new PostingReportReader())->lines(1, '2026-10-31', '2026-10-31'));
        $this->assertEqualsWithDelta(0, (float) DB::table('finance_posting_lines')->sum('debit')
            - (float) DB::table('finance_posting_lines')->sum('credit'), 0.001);
        $this->assertSame('125.5', (string) DB::table('finance_posting_lines')
            ->where('posting_batch_id', $original)->sum('debit'));
    }

    public function test_v2_reversal_idempotent_replay_and_different_key_are_protected(): void
    {
        $original = (new JournalPostingWriter())->post(1, 51, 'JV-REVERSAL-ORIGINAL-02');
        $writer = new PostingReversalWriter();
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
        $original = (new JournalPostingWriter())->post(1, 51, 'JV-REVERSAL-ORIGINAL-04');
        $writer = new PostingReversalWriter();
        $reversal = $writer->reverse(1, $original, 'JV-REVERSAL-REQUEST-04', '2026-10-31', 'Correction');
        DB::table('finance_posting_lines')->where('posting_batch_id', $reversal)
            ->where('project_head_subhead_id', 101)->update(['credit' => '126.50']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REVERSAL_REPLAY_TAMPERED');
        $writer->reverse(1, $original, 'JV-REVERSAL-REQUEST-04', '2026-10-31', 'Correction');
    }

    public function test_v2_reversal_refuses_reversal_of_reversal_and_invalid_origin(): void
    {
        $original = (new JournalPostingWriter())->post(1, 51, 'JV-REVERSAL-ORIGINAL-05');
        $reversal = (new PostingReversalWriter())->reverse(
            1, $original, 'JV-REVERSAL-REQUEST-05', '2026-10-31', 'Correction'
        );
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REVERSAL_ORIGINAL_NOT_POSTED');
        (new PostingReversalWriter())->reverse(
            1, $reversal, 'JV-REVERSAL-REQUEST-06', '2026-10-31', 'Correction'
        );
    }

    public function test_v2_reversal_rejects_unbalanced_origin_without_mutation(): void
    {
        $original = (new JournalPostingWriter())->post(1, 51, 'JV-REVERSAL-ORIGINAL-07');
        DB::table('finance_posting_lines')->where('posting_batch_id', $original)
            ->where('project_head_subhead_id', 101)->update(['debit' => '126.50']);
        try {
            (new PostingReversalWriter())->reverse(
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
        $original = (new JournalPostingWriter())->post(1, 51, 'JV-REVERSAL-ORIGINAL-08');
        (new PostingReversalWriter())->reverse(
            1, $original, 'JV-REVERSAL-REQUEST-08', '2026-10-31', 'Correction'
        );
        DB::table('finance_posting_journal_sources')->where('posting_batch_id', $original)->delete();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REPORT_MISSING_TYPED_SOURCE_LINK');
        (new PostingReportReader())->lines(1);
    }

    public function test_v2_report_refuses_balanced_but_not_inverse_reversal(): void
    {
        $original = (new JournalPostingWriter())->post(1, 51, 'JV-REVERSAL-ORIGINAL-09');
        $reversal = (new PostingReversalWriter())->reverse(
            1, $original, 'JV-REVERSAL-REQUEST-09', '2026-10-31', 'Correction'
        );
        DB::table('finance_posting_lines')->where('posting_batch_id', $reversal)
            ->where('project_head_subhead_id', 101)->update(['credit' => '500.00']);
        DB::table('finance_posting_lines')->where('posting_batch_id', $reversal)
            ->where('project_head_subhead_id', 102)->update(['debit' => '500.00']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REPORT_REVERSAL_NOT_EXACT_INVERSE');
        (new PostingReportReader())->lines(1, '2026-10-31', '2026-10-31');
    }

    public function test_v2_reversal_cannot_predate_original_posting(): void
    {
        $original = (new JournalPostingWriter())->post(
            1, 51, 'JV-REVERSAL-CHRONOLOGY-01'
        );
        try {
            (new PostingReversalWriter())->reverse(
                1, $original, 'JV-REVERSAL-CHRONOLOGY-02', '2026-10-29', 'Correction'
            );
            $this->fail('Backdated reversal was accepted');
        } catch (\DomainException $e) {
            $this->assertSame('V2_REVERSAL_DATE_BEFORE_ORIGINAL', $e->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_batches', 1);
        $this->assertDatabaseCount('finance_posting_lines', 2);
    }

    public function test_v2_gateway_exposes_one_typed_posting_reversal_and_reporting_boundary(): void
    {
        $gateway = new PostingGateway();
        $original = $gateway->postJournal(1, 51, 'JV-GATEWAY-ORIGINAL-0001');
        $this->assertSame($original, $gateway->postJournal(1, 51, 'JV-GATEWAY-ORIGINAL-0001'));
        $reversed = $gateway->reversePostedBatch(
            1, $original, 'JV-GATEWAY-REVERSE-0001', '2026-10-31', 'Correction'
        );
        $this->assertNotSame($original, $reversed);
        $this->assertCount(4, $gateway->postingLines(1));
        $this->assertSame([], $gateway->postingLines(2));
        $this->assertDatabaseCount('finance_posting_source_claims', 1);
        $this->assertDatabaseCount('finance_posting_journal_sources', 1);
    }

    public function test_v2_trial_balance_includes_opening_period_and_immutable_reversal(): void
    {
        $gateway = new PostingGateway();
        $original = $gateway->postJournal(1, 51, 'JV-TRIAL-BALANCE-ORIGINAL-01');
        $before = $gateway->trialBalance(1, '2026-10-30', '2026-10-30');
        $this->assertSame('typed_v2_postings_only', $before['scope']);
        $this->assertSame('125.50', $before['total_period_debit']);
        $this->assertSame('125.50', $before['total_period_credit']);
        $this->assertSame('125.50', $before['accounts'][0]['closing_balance']);
        $this->assertSame('-125.50', $before['accounts'][1]['closing_balance']);

        $gateway->reversePostedBatch(
            1, $original, 'JV-TRIAL-BALANCE-REVERSE-01', '2026-10-31', 'Correction'
        );
        $after = $gateway->trialBalance(1, '2026-10-31', '2026-10-31');
        $this->assertTrue($after['balanced']);
        $this->assertSame('125.50', $after['total_period_debit']);
        $this->assertSame('125.50', $after['total_period_credit']);
        $this->assertSame('125.50', $after['accounts'][0]['opening_balance']);
        $this->assertSame('-125.50', $after['accounts'][1]['opening_balance']);
        $this->assertSame('0.00', $after['accounts'][0]['closing_balance']);
        $this->assertSame('0.00', $after['accounts'][1]['closing_balance']);
        $this->assertSame([], $gateway->trialBalance(2)['accounts']);
    }

    public function test_v2_trial_balance_rejects_invalid_interval_without_posting(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PostingGateway())->trialBalance(1, '2026-11-01', '2026-10-30');
    }

    public function test_v2_account_statement_reconciles_opening_period_and_reversal_running_balance(): void
    {
        $gateway = new PostingGateway();
        $original = $gateway->postJournal(1, 51, 'JV-ACCOUNT-STATEMENT-ORIGINAL-1');

        $prior = $gateway->accountStatement(1, 101, '2026-10-31', '2026-10-31');
        $this->assertSame('125.50', $prior['opening_balance']);
        $this->assertSame('0.00', $prior['period_debit']);
        $this->assertSame('125.50', $prior['closing_balance']);
        $this->assertSame([], $prior['lines']);

        $gateway->reversePostedBatch(
            1, $original, 'JV-ACCOUNT-STATEMENT-REVERSE-01', '2026-10-31', 'Correction'
        );
        $account = $gateway->accountStatement(1, 101, '2026-10-31', '2026-10-31');
        $this->assertSame('125.50', $account['opening_balance']);
        $this->assertSame('125.50', $account['period_credit']);
        $this->assertSame('0.00', $account['closing_balance']);
        $this->assertCount(1, $account['lines']);
        $this->assertSame('journal', $account['lines'][0]['source_kind']);
        $this->assertSame(51, $account['lines'][0]['source_id']);
        $this->assertTrue($account['lines'][0]['is_reversal']);
        $this->assertSame('0.00', $account['lines'][0]['running_balance']);
    }

    public function test_v2_account_statement_denies_foreign_project_account(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_ACCOUNT_STATEMENT_PROJECT_OWNERSHIP_MISMATCH');
        (new PostingGateway())->accountStatement(1, 201);
    }

    public function test_sales_voucher_uses_real_journal_header_and_details_with_same_typed_fk(): void
    {
        DB::table('journal_vouchers')->where('id', 51)->update(['type' => 'SV']);
        $gateway = new PostingGateway();
        $id = $gateway->postSalesVoucher(1, 51, 'SV-REAL-JOURNAL-POST-0001');
        $this->assertSame($id, $gateway->postSalesVoucher(1, 51, 'SV-REAL-JOURNAL-POST-0001'));
        $this->assertDatabaseHas('finance_posting_batches', [
            'id' => $id, 'project_id' => 1,
            'voucher_series' => 'SV', 'voucher_number' => 51,
        ]);
        $this->assertDatabaseHas('finance_posting_journal_sources', [
            'posting_batch_id' => $id, 'journal_voucher_id' => 51,
        ]);
        $this->assertDatabaseCount('finance_journal_line_sources', 2);
        $this->assertSame('journal', $gateway->postingLines(1)[0]['source_kind']);
        $this->assertSame('SV', $gateway->postingLines(1)[0]['voucher_series']);
        $this->assertSame('125.50', $gateway->postingSummary(1)['total_debit']);
        $this->assertSame('51', $gateway->voucherRegister(1)[0]['source_reference']);
    }

    public function test_sv_same_batch_correction_and_ordinary_delete_preserve_exact_fk_history(): void
    {
        DB::table('journal_vouchers')->where('id',51)->update(['type'=>'SV']);
        $key='SV-CORRECTION-LIFECYCLE-0001';
        $gateway=new PostingGateway();
        $batch=$gateway->postSalesVoucher(1,51,$key);
        $editor=new \App\Services\Finance\JournalApprovedVoucherEditor();
        $editor->update(1,51,'2026-10-30',[
            ['detail_id'=>511,'account_id'=>101,'debit'=>'150.50','credit'=>'0.00',
                'description'=>'Corrected sales debit'],
            ['detail_id'=>512,'account_id'=>102,'debit'=>'0.00','credit'=>'150.50',
                'description'=>'Corrected sales credit'],
        ],[],null);
        self::assertSame(1,DB::table('finance_posting_batches')->count());
        self::assertSame(2,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->where('is_active',1)->count());
        self::assertSame(150.5,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->where('is_active',1)->sum('debit'));
        self::assertSame($batch,$gateway->postSalesVoucher(1,51,$key));
        (new \App\Services\Finance\PostedVoucherArchiver())->archive(1,'journal',51);
        self::assertNotNull(DB::table('journal_vouchers')->where('id',51)
            ->value('deleted_at'));
        self::assertSame(0,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->where('is_active',1)->count());
        self::assertSame(1,DB::table('finance_posting_batches')->count());
        self::assertSame([], $gateway->postingLines(1));
    }

    public function test_sales_voucher_create_uses_own_counter_and_retries_without_new_source(): void
    {
        // Extend only this ephemeral SQLite fixture to exercise the real
        // SV create workflow, without altering project/customer test data.
        Schema::table('customer_ledger',function(Blueprint $table){
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('transaction_type')->nullable();
            $table->unsignedBigInteger('type_id')->nullable();
        });
        // This fixture must include the real detail-ledger linking columns.
        // A minimal "ledgers" table without the attribution table previously
        // skipped the linker while the strict posting gate correctly rejected
        // the unlinked SV posting.
        Schema::create('ledgers',function(Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('journal_voucher_detail_id')->nullable()->unique();
            $table->unsignedBigInteger('project_head_subheads_id');
            $table->string('type');
            $table->unsignedBigInteger('type_id')->nullable();
            $table->unsignedBigInteger('voucher_number')->nullable();
            $table->string('reference')->nullable();
            $table->date('date')->nullable();
            $table->decimal('amount_in',15,2)->default(0);
            $table->decimal('amount_out',15,2)->default(0);
            $table->text('detail')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('0');
            $table->unsignedBigInteger('create_by')->nullable();
            $table->timestamps();
        });
        Schema::create('ledger_source_attributions',function(Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('ledger_id')->unique();
            $table->unsignedBigInteger('project_id');
            $table->string('source_kind',32);
            $table->unsignedBigInteger('source_id');
            $table->string('evidence_kind',48);
            $table->string('evidence_reference',191)->nullable();
            $table->unsignedBigInteger('attributed_by')->nullable();
            $table->timestamps();
        });
        Schema::table('journal_vouchers',function(Blueprint $table){
            $table->unsignedBigInteger('created_by')->nullable();
        });
        Schema::table('journal_voucher_details',function(Blueprint $table){
            $table->unsignedBigInteger('created_by')->nullable();
        });
        $data=[
            'date'=>'2026-11-19','reference'=>'SALES-TEST-1',
            'description'=>'Independent sales voucher',
            'accounts'=>[1,1],'sub_accounts'=>[101,102],
            'debit'=>['250.00','0'],'credit'=>['0','250.00'],
            'line_description'=>['Sales debit','Sales credit'],
        ];
        $workflow=new \App\Services\Finance\OriginalJournalWorkflow();
        $key='sales-voucher-create-2026-0001';
        $id=$workflow->createAndPost(1,$key,$data,null,'SV');
        self::assertGreaterThan(51,$id);
        self::assertSame($id,$workflow->createAndPost(1,$key,$data,null,'SV'));
        self::assertSame(1,DB::table('journal_vouchers')
            ->where('type','SV')->where('project_id',1)->count());
        self::assertSame(2,DB::table('journal_voucher_details')
            ->where('journal_voucher_id',$id)->count());
        $batch=DB::table('finance_posting_journal_sources')
            ->where('journal_voucher_id',$id)->value('posting_batch_id');
        self::assertNotNull($batch);
        self::assertSame('SV',DB::table('finance_posting_batches')
            ->where('id',$batch)->value('voucher_series'));
        self::assertSame(2,DB::table('finance_journal_line_sources')
            ->join('finance_posting_lines as line','line.id','=','finance_journal_line_sources.posting_line_id')
            ->where('line.posting_batch_id',$batch)->count());
        // Both posted accounting legs must have distinct real legacy ledgers
        // and exact source attributions, including on idempotent replay.
        self::assertSame(2,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->whereNotNull('legacy_ledger_id')->count());
        self::assertSame(2,DB::table('ledgers')->where('type','SV')->where('type_id',$id)->count());
        self::assertSame(2,DB::table('ledger_source_attributions')
            ->where('project_id',1)->where('source_kind','journal_voucher')
            ->where('source_id',$id)->count());
        \App\Services\Finance\PostedVoucherLedgerGate::assertLinked(1,(int)$batch);
        self::assertSame(1,DB::table('finance_posting_batches')->count());
        self::assertSame(250.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->sum('credit'));
        self::assertSame(250.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->sum('debit'));
        $changed=$data;
        $changed['description']='Tampered request replay';
        try{
            $workflow->createAndPost(1,$key,$changed,null,'SV');
            self::fail('An idempotency key cannot silently change the stored SV.');
        }catch(\DomainException $e){
            self::assertStringContainsString('REQUEST_CONFLICT',$e->getMessage());
        }
        self::assertSame(1,DB::table('finance_posting_batches')->count());
        self::assertSame(1,DB::table('journal_vouchers')->where('type','SV')->count());
    }

    public function test_jv_writer_refuses_sales_document_without_explicit_sv_series(): void
    {
        DB::table('journal_vouchers')->where('id', 51)->update(['type' => 'SV']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_JV_HEADER_NOT_ACTIVE');
        (new PostingGateway())->postJournal(1, 51, 'SV-INVALID-JV-POST-0001');
    }

}
