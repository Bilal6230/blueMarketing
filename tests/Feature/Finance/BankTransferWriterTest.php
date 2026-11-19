<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\BankTransferWriter;
use App\Services\Finance\PostingReversalWriter;
use App\Services\Finance\PostingReportReader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BankTransferWriterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.env', 'testing');
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.sqlite.foreign_key_constraints', true);
        DB::purge('sqlite'); DB::setDefaultConnection('sqlite'); DB::statement('PRAGMA foreign_keys = ON');
        foreach ([
            'projects', 'users', 'journal_vouchers', 'journal_voucher_details', 'commision_vouchers',
            'stock_entries', 'stock_bills', 'booking_vouchers', 'labour_ledgers',
        ] as $name) {
            Schema::create($name, function (Blueprint $t): void { $t->id(); });
        }
        Schema::create('customer_ledger', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->string('transaction_type'); $t->unsignedTinyInteger('payment_type');
            $t->unsignedBigInteger('type_id'); $t->date('date'); $t->unsignedBigInteger('customer_id')->nullable(); $t->unsignedBigInteger('plot_id')->nullable();
            $t->string('reference')->nullable(); $t->text('description')->nullable(); $t->unsignedBigInteger('custom_payment_method_id')->nullable();
            $t->string('t_number')->nullable(); $t->unsignedBigInteger('bank_id')->nullable(); $t->dateTime('passing_date')->nullable(); $t->string('cheque_purpose')->nullable();
            $t->decimal('amount_in', 18, 2)->default(0); $t->decimal('amount_out', 18, 2)->default(0);
            $t->boolean('is_active')->default(true); $t->unsignedTinyInteger('passing_status')->default(0);
        });
        // The cheque integrity guard queries legacy ledger custody before posting.
        // Model only the columns needed by this isolated in-memory integration test.
        Schema::create('ledgers', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('customer_ledger_id')->nullable()->index();
        });
        Schema::create('finance_original_customer_requests', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('customer_ledger_id'); $t->string('source_sha256');
        });
        Schema::create('head_accountings', function (Blueprint $t): void { $t->id(); $t->string('name'); $t->boolean('is_active')->default(true); });
        Schema::create('subhead_accountings', function (Blueprint $t): void { $t->id(); $t->boolean('is_active')->default(true); });
        Schema::create('project_head_subheads', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('head_accounting_id');
            $t->unsignedBigInteger('subhead_accounting_id'); $t->unique(['id', 'project_id']);
        });
        (require base_path('database/finance_legacy_migrations/2026_10_30_000100_create_finance_finance_posting_core.php'))->up();
        if (!Schema::hasColumn('finance_posting_lines','is_active')) {
            Schema::table('finance_posting_lines',function(Blueprint $table){
                $table->boolean('is_active')->default(true);
            });
        }
        Schema::create('project_bank_accounts', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('account_mapping_id');
            $t->boolean('is_active')->default(true); $t->unique(['id', 'project_id']);
            $t->foreign(['account_mapping_id', 'project_id'])->references(['id', 'project_id'])->on('project_head_subheads');
        });
        (require base_path('database/finance_legacy_migrations/2026_11_05_000200_create_finance_bank_transfer_sources.php'))->up();
        Schema::table('finance_bank_transfer_sources',function(Blueprint $table){
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
        });
        DB::table('projects')->insert([['id' => 1], ['id' => 2]]);
        DB::table('head_accountings')->insert([['id' => 10, 'name' => 'Bank Account'], ['id' => 20, 'name' => 'Cash in Hand']]);
        DB::table('subhead_accountings')->insert([['id' => 10], ['id' => 20]]);
        DB::table('project_head_subheads')->insert([
            ['id' => 101, 'project_id' => 1, 'head_accounting_id' => 10, 'subhead_accounting_id' => 10],
            ['id' => 102, 'project_id' => 1, 'head_accounting_id' => 20, 'subhead_accounting_id' => 20],
            ['id' => 201, 'project_id' => 2, 'head_accounting_id' => 10, 'subhead_accounting_id' => 10],
        ]);
        DB::table('project_bank_accounts')->insert([
            ['id' => 1, 'project_id' => 1, 'account_mapping_id' => 101],
            ['id' => 2, 'project_id' => 2, 'account_mapping_id' => 201],
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite'); parent::tearDown();
    }

    public function test_deposit_and_withdrawal_post_balanced_direction_and_are_idempotent(): void
    {
        $writer = new BankTransferWriter();
        $deposit = $writer->post(1, 1, 'deposit', 'BANK-TRANSFER-REQUEST-0001', '2026-11-05', '100.00',
            'Cash deposited to bank', [['account_id' => 102, 'amount' => '60'], ['account_id' => 102, 'amount' => '40']]);
        $again = $writer->post(1, 1, 'deposit', 'BANK-TRANSFER-REQUEST-0001', '2026-11-05', '100.00',
            'Cash deposited to bank', [['account_id' => 102, 'amount' => '60'], ['account_id' => 102, 'amount' => '40']]);
        $withdrawal = $writer->post(1, 1, 'withdrawal', 'BANK-TRANSFER-REQUEST-0002', '2026-11-05', '25.50',
            'Cash withdrawn from bank', [['account_id' => 102, 'amount' => '25.50']]);
        $this->assertFalse($deposit['replayed']); $this->assertTrue($again['replayed']);
        $this->assertNotSame($deposit['posting_batch_id'], $withdrawal['posting_batch_id']);
        $this->assertSame(100.0, (float) DB::table('finance_posting_lines')->where('posting_batch_id', $deposit['posting_batch_id'])->where('project_head_subhead_id', 101)->value('debit'));
        $this->assertSame(25.5, (float) DB::table('finance_posting_lines')->where('posting_batch_id', $withdrawal['posting_batch_id'])->where('project_head_subhead_id', 101)->value('credit'));
        $this->assertSame('bank_transfer', DB::table('finance_posting_source_claims')->where('posting_batch_id', $deposit['posting_batch_id'])->value('source_kind'));
        $this->assertCount(5, (new PostingReportReader())->lines(1));
        $register = (new \App\Services\Finance\PostingGateway())->voucherRegister(1);
        $bankDocuments = array_values(array_filter($register, fn ($document) => $document['source_kind'] === 'bank_transfer'));
        $this->assertCount(2, $bankDocuments);
        $this->assertContains('BD-1', array_column($bankDocuments, 'source_reference'));
        $this->assertContains('BW-2', array_column($bankDocuments, 'source_reference'));
        $ledgerRows = (new \App\Services\Finance\OriginalLedgerReport())->rows(1, null, null);
        $this->assertSame(5, count($ledgerRows));
        $this->assertContains('bank_transfer', array_column($ledgerRows, 'source_kind'));
    }

    public function test_manual_bank_transfer_can_be_corrected_on_same_batch_with_no_duplicate_active_posting(): void
    {
        $writer=new BankTransferWriter();
        $original=$writer->post(1,1,'deposit','BANK-TRANSFER-EDIT-0001',
            '2026-11-05','100.00','Original',
            [['account_id'=>102,'amount'=>'100.00']]);
        $id=$original['posting_batch_id'];
        $editor=new \App\Services\Finance\BankTransferEditor();
        $editor->update(1,'deposit',$id,1,102,'2026-11-06','150.00','Corrected');
        self::assertSame(1,DB::table('finance_posting_batches')->count());
        self::assertSame(4,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$id)->count());
        self::assertSame(2,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$id)->where('is_active',1)->count());
        self::assertSame(150.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$id)->where('is_active',1)->sum('debit'));
        self::assertSame(150.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$id)->where('is_active',1)->sum('credit'));
        self::assertSame(150.0,(float)DB::table('finance_bank_transfer_sources')
            ->where('posting_batch_id',$id)->value('amount'));
        self::assertCount(2,(new PostingReportReader())->lines(1));
        self::assertSame([],array_filter((new \App\Services\Finance\BankTransferReconciliation())->audit(1),
            fn($row)=>!$row['ok']));

        $editor->update(1,'deposit',$id,1,102,'2026-11-06','150.00','Corrected');
        self::assertSame(4,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$id)->count());
        $editor->update(1,'deposit',$id,1,102,'2026-11-07','90.00','Changed again');
        self::assertSame(6,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$id)->count());
        self::assertSame(90.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$id)->where('is_active',1)->sum('debit'));
        self::assertSame(1,DB::table('finance_posting_batches')->count());
    }

    public function test_manual_bank_correction_rejects_foreign_project_and_reversed_source(): void
    {
        $writer=new BankTransferWriter();
        $id=$writer->post(1,1,'withdrawal','BANK-TRANSFER-EDIT-0002',
            '2026-11-05','20.00','Cash withdrawal',
            [['account_id'=>102,'amount'=>'20.00']])['posting_batch_id'];
        $editor=new \App\Services\Finance\BankTransferEditor();
        try{
            $editor->update(2,'withdrawal',$id,2,201,'2026-11-06','30.00','Foreign');
            self::fail('Cross-project update must be blocked.');
        }catch(\Throwable $e){
            self::assertTrue($e instanceof \DomainException
                ||$e instanceof \Illuminate\Database\RecordNotFoundException);
        }
        self::assertSame('20.00',number_format((float)DB::table('finance_bank_transfer_sources')
            ->where('posting_batch_id',$id)->value('amount'),2,'.',''));
        (new PostingReversalWriter())->reverse(1,$id,'BANK-TRANSFER-EDIT-REV-0002',
            '2026-11-06','Reversed');
        try {
            $editor->update(1,'withdrawal',$id,1,102,'2026-11-07','30.00','Wrong');
            self::fail('Reversed bank transfer must not be editable.');
        }catch(\DomainException $e){
            self::assertStringContainsString('invalid',$e->getMessage());
        }
        self::assertSame(20.0,(float)DB::table('finance_bank_transfer_sources')
            ->where('posting_batch_id',$id)->value('amount'));
    }

    public function test_manual_bank_delete_archives_exact_source_and_keeps_customer_report_and_audit_clean(): void
    {
        $writer=new BankTransferWriter();
        $id=$writer->post(1,1,'deposit','BANK-TRANSFER-DELETE-0001',
            '2026-11-05','120.00','Manual bank deposit',
            [['account_id'=>102,'amount'=>'120.00']])['posting_batch_id'];
        $editor=new \App\Services\Finance\BankTransferEditor();
        $editor->delete(1,'deposit',$id);
        self::assertSame(1,DB::table('finance_posting_batches')->count());
        self::assertSame(1,DB::table('finance_bank_transfer_sources')->count());
        self::assertSame(0,(int)DB::table('finance_bank_transfer_sources')
            ->where('posting_batch_id',$id)->value('is_active'));
        self::assertNotNull(DB::table('finance_bank_transfer_sources')
            ->where('posting_batch_id',$id)->value('deleted_at'));
        self::assertSame(2,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$id)->count());
        self::assertSame(0,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$id)->where('is_active',1)->count());
        self::assertSame([],(new PostingReportReader())->lines(1));
        self::assertSame([],(new \App\Services\Finance\BankTransferReconciliation())->audit(1));
        self::assertSame([],(new \App\Services\Finance\PostingBatchIntegrityAudit())->audit(1));
        try {
            $editor->delete(1,'deposit',$id);
            self::fail('Archived bank voucher must not be deleted again.');
        } catch(\DomainException $e) {
            self::assertStringContainsString('not an active',$e->getMessage());
        }
        try {
            $writer->post(1,1,'deposit','BANK-TRANSFER-DELETE-0001',
                '2026-11-05','120.00','Manual bank deposit',
                [['account_id'=>102,'amount'=>'120.00']]);
            self::fail('Archived bank voucher must not be resurrected by replay.');
        } catch(\DomainException $e) {
            self::assertSame('V2_BANK_TRANSFER_IDEMPOTENCY_CONFLICT',$e->getMessage());
        }
    }

    public function test_archived_bank_active_leg_tamper_is_reported_not_silently_ignored(): void
    {
        $id=(new BankTransferWriter())->post(1,1,'withdrawal',
            'BANK-TRANSFER-TAMPER-DELETE-0001','2026-11-05',
            '45.00','Manual',[['account_id'=>102,'amount'=>'45.00']])['posting_batch_id'];
        (new \App\Services\Finance\BankTransferEditor())->delete(1,'withdrawal',$id);
        DB::table('finance_posting_lines')->where('posting_batch_id',$id)
            ->orderBy('id')->limit(1)->update(['is_active'=>1]);
        $findings=(new \App\Services\Finance\PostingBatchIntegrityAudit())->audit(1);
        self::assertCount(1,$findings);
        self::assertContains('ARCHIVED_BANK_WITH_ACTIVE_POSTINGS',$findings[0]['issues']);
        $this->expectException(\DomainException::class);
        (new PostingReportReader())->lines(1);
    }

    public function test_wrong_project_mapping_and_unbalanced_allocations_fail_without_posting(): void
    {
        $writer = new BankTransferWriter();
        try {
            $writer->post(1, 2, 'deposit', 'BANK-TRANSFER-REQUEST-0003', '2026-11-05', '10', 'Wrong bank', [['account_id' => 102, 'amount' => '10']]);
            $this->fail('Foreign project bank must be rejected');
        } catch (\DomainException $e) { $this->assertSame('V2_BANK_TRANSFER_ACCOUNT_PROJECT_MISMATCH', $e->getMessage()); }
        try {
            $writer->post(1, 1, 'withdrawal', 'BANK-TRANSFER-REQUEST-0004', '2026-11-05', '10', 'Mismatch', [['account_id' => 102, 'amount' => '9']]);
            $this->fail('Unbalanced allocation must be rejected');
        } catch (\DomainException $e) { $this->assertSame('V2_BANK_TRANSFER_ALLOCATION_TOTAL_MISMATCH', $e->getMessage()); }
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_bank_transfer_reversal_preserves_original_source_and_lines(): void
    {
        $batch = (new BankTransferWriter())->post(1, 1, 'deposit', 'BANK-TRANSFER-REQUEST-0005', '2026-11-05', '10',
            'Deposit', [['account_id' => 102, 'amount' => '10']])['posting_batch_id'];
        $reversal = (new PostingReversalWriter())->reverse(1, $batch, 'BANK-TRANSFER-REVERSAL-01', '2026-11-06', 'Approved correction');
        $this->assertDatabaseCount('finance_bank_transfer_sources', 1);
        $this->assertDatabaseCount('finance_posting_lines', 4);
        $rows = (new PostingReportReader())->lines(1, '2026-11-06', '2026-11-06');
        $this->assertCount(2, $rows); $this->assertSame('bank_transfer', $rows[0]['source_kind']);
        $this->assertSame($batch, (int) $rows[0]['reversal_of_batch_id']);
        $this->assertSame('REV', DB::table('finance_posting_batches')->where('id', $reversal)->value('voucher_series'));
    }

    public function test_withdrawal_can_link_a_verified_cleared_cheque_at_its_exact_amount_once(): void
    {
        $source = (object) ['id'=>501, 'project_id'=>1, 'transaction_type'=>'CP', 'type_id'=>44, 'date'=>'2026-11-04', 'customer_id'=>null,
            'plot_id'=>null, 'reference'=>null, 'description'=>'Cheque withdrawal', 'payment_type'=>3, 'custom_payment_method_id'=>null,
            't_number'=>'CHQ-44', 'bank_id'=>null, 'passing_date'=>null, 'cheque_purpose'=>'Cash withdrawal', 'amount_in'=>'0.00', 'amount_out'=>'25.50',
            'is_active'=>1, 'passing_status'=>1];
        DB::table('customer_ledger')->insert((array) $source);
        DB::table('finance_original_customer_requests')->insert(['project_id'=>1, 'customer_ledger_id'=>501,
            'source_sha256'=>\App\Services\Finance\OriginalCustomerWorkflow::fingerprint($source)]);
        $writer = new BankTransferWriter();
        try {
            $writer->post(1, 1, 'withdrawal', 'BANK-CHEQUE-REQUEST-0003', '2026-11-05', '25.49', 'Wrong amount',
                [['account_id'=>102, 'amount'=>'25.49']], null, 501);
            $this->fail('Cheque amount must be immutable.');
        } catch (\DomainException $e) { $this->assertSame('V2_BANK_TRANSFER_CHEQUE_AMOUNT_MISMATCH', $e->getMessage()); }
        $result = $writer->post(1, 1, 'withdrawal', 'BANK-CHEQUE-REQUEST-0001', '2026-11-05', '25.50', 'Cash withdrawn',
            [['account_id'=>102, 'amount'=>'25.50']], null, 501);
        $this->assertSame(501, (int) DB::table('finance_bank_transfer_sources')->where('posting_batch_id', $result['posting_batch_id'])->value('linked_customer_ledger_id'));
        $this->assertSame(25.5, (float) DB::table('finance_posting_lines')->where('posting_batch_id', $result['posting_batch_id'])->where('project_head_subhead_id', 102)->value('debit'));
        try {
            $writer->post(1, 1, 'withdrawal', 'BANK-CHEQUE-REQUEST-0002', '2026-11-05', '25.50', 'Duplicate cheque',
                [['account_id'=>102, 'amount'=>'25.50']], null, 501);
            $this->fail('A cleared cheque may only be linked once.');
        } catch (\DomainException $e) { $this->assertSame('V2_BANK_TRANSFER_CHEQUE_ALREADY_LINKED', $e->getMessage()); }
    }
    public function test_read_only_reconciliation_checks_deposit_withdrawal_and_project_isolation(): void
    {
        $writer = new BankTransferWriter();
        $writer->post(1, 1, 'deposit', 'BANK-AUDIT-REQUEST-0001', '2026-11-05', '100.00',
            'Bank deposit', [['account_id' => 102, 'amount' => '100.00']]);
        $writer->post(1, 1, 'withdrawal', 'BANK-AUDIT-REQUEST-0002', '2026-11-05', '25.50',
            'Bank withdrawal', [['account_id' => 102, 'amount' => '25.50']]);
        $audit = new \App\Services\Finance\BankTransferReconciliation();
        $rows = $audit->audit(1);
        $this->assertCount(2, $rows);
        $this->assertSame(['deposit', 'withdrawal'], array_column($rows, 'kind'));
        $this->assertSame(['100.00', '25.50'], array_column($rows, 'amount'));
        $this->assertSame(['100.00', '25.50'], array_column($rows, 'debit'));
        $this->assertSame(['100.00', '25.50'], array_column($rows, 'credit'));
        $this->assertSame([true, true], array_column($rows, 'ok'));
        $this->assertSame([], $audit->audit(2));
        $this->assertDatabaseCount('finance_posting_batches', 2);
        $this->assertDatabaseCount('finance_posting_lines', 4);
    }

    public function test_read_only_reconciliation_flags_amount_and_direction_corruption_without_repairing(): void
    {
        $batch = (new BankTransferWriter())->post(1, 1, 'deposit', 'BANK-AUDIT-REQUEST-0003',
            '2026-11-05', '75.00', 'Deposit', [['account_id' => 102, 'amount' => '75.00']])['posting_batch_id'];
        DB::table('finance_posting_lines')->where('posting_batch_id', $batch)
            ->where('project_head_subhead_id', 101)->update(['debit' => '0.00', 'credit' => '75.00']);
        $rows = (new \App\Services\Finance\BankTransferReconciliation())->audit(1);
        $this->assertCount(1, $rows);
        $this->assertFalse($rows[0]['ok']);
        $this->assertContains('BANK_DIRECTION_MISMATCH', $rows[0]['issues']);
        $this->assertContains('UNBALANCED_POSTING', $rows[0]['issues']);
        $this->assertContains('SOURCE_AMOUNT_MISMATCH', $rows[0]['issues']);
        $this->assertEquals(75, (float) DB::table('finance_posting_lines')->where('posting_batch_id', $batch)
            ->where('project_head_subhead_id', 101)->value('credit'));
    }

    public function test_read_only_reconciliation_flags_missing_source_claim(): void
    {
        $batch = (new BankTransferWriter())->post(1, 1, 'withdrawal', 'BANK-AUDIT-REQUEST-0004',
            '2026-11-05', '10.00', 'Withdrawal', [['account_id' => 102, 'amount' => '10.00']])['posting_batch_id'];
        DB::table('finance_posting_source_claims')->where('posting_batch_id', $batch)->delete();
        $rows = (new \App\Services\Finance\BankTransferReconciliation())->audit(1);
        $this->assertContains('SOURCE_CLAIM_MISMATCH', $rows[0]['issues']);
        $this->assertFalse($rows[0]['ok']);
    }

}
