<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\V2CashPostingWriter;
use App\Services\Finance\V2PostingReversalWriter;
use App\Services\Finance\V2PostingReportReader;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class V2CashPostingWriterTest extends TestCase
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
            'projects', 'users', 'journal_vouchers', 'journal_voucher_details',
            'commision_vouchers', 'stock_entries', 'stock_bills',
            'booking_vouchers', 'labour_ledgers',
        ] as $name) {
            Schema::create($name, function (Blueprint $t): void { $t->id(); });
        }
        Schema::create('project_head_subheads', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('project_id');
        });
        Schema::create('customer_ledger', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->string('transaction_type');
            $t->unsignedBigInteger('type_id')->nullable();
            $t->date('date');
            $t->decimal('amount_in', 18, 2)->default(0);
            $t->decimal('amount_out', 18, 2)->default(0);
            $t->boolean('is_active')->default(true);
        });
        $migration = require base_path('database/v2_migrations/2026_10_30_000100_create_v2_finance_posting_core.php');
        $migration->up();

        DB::table('projects')->insert([['id' => 1], ['id' => 2]]);
        DB::table('project_head_subheads')->insert([
            ['id' => 101, 'project_id' => 1],
            ['id' => 102, 'project_id' => 1],
            ['id' => 201, 'project_id' => 2],
        ]);
        DB::table('customer_ledger')->insert([
            ['id' => 42, 'project_id' => 1, 'transaction_type' => 'CR',
             'type_id' => 7, 'date' => '2026-10-30',
             'amount_in' => '250.00', 'amount_out' => '0.00'],
            ['id' => 43, 'project_id' => 1, 'transaction_type' => 'CP',
             'type_id' => 8, 'date' => '2026-10-30',
             'amount_in' => '0.00', 'amount_out' => '80.00'],
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function crLegs(): array
    {
        return [
            ['account_id' => 101, 'debit' => '250.00', 'credit' => '0.00'],
            ['account_id' => 102, 'debit' => '0.00', 'credit' => '250.00'],
        ];
    }

    public function test_cash_CR_uses_actual_customer_source_FK_and_replays_idempotently(): void
    {
        $writer = new V2CashPostingWriter();
        $first = $writer->post(1, 42, 'CASH-REQUEST-000001', $this->crLegs());
        $again = $writer->post(1, 42, 'CASH-REQUEST-000001', $this->crLegs());
        $this->assertSame($first, $again);
        $this->assertDatabaseCount('finance_posting_batches', 1);
        $this->assertDatabaseCount('finance_posting_lines', 2);
        $this->assertDatabaseCount('finance_posting_cash_sources', 1);
        $this->assertSame('cash', DB::table('finance_posting_source_claims')->value('source_kind'));
        $this->assertSame(42, (int) DB::table('finance_posting_cash_sources')->value('customer_ledger_id'));
        $report = (new V2PostingReportReader())->lines(1);
        $this->assertCount(2, $report);
        $this->assertSame(42, $report[0]['source_id']);
        $this->assertSame('CR', $report[0]['voucher_series']);
    }

    public function test_cash_wrong_project_account_fails_without_any_batch(): void
    {
        $bad = $this->crLegs();
        $bad[0]['account_id'] = 201;
        try {
            (new V2CashPostingWriter())->post(1, 42, 'CASH-REQUEST-000002', $bad);
            $this->fail('Expected project account rejection');
        } catch (\DomainException $e) {
            $this->assertSame('V2_CASH_ACCOUNT_PROJECT_MISMATCH', $e->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_cash_wrong_total_and_wrong_source_project_are_rejected(): void
    {
        $bad = $this->crLegs();
        $bad[1]['credit'] = '249.00';
        try {
            (new V2CashPostingWriter())->post(1, 42, 'CASH-REQUEST-000003', $bad);
            $this->fail('Expected unbalanced amount');
        } catch (\DomainException $e) {
            $this->assertSame('V2_CASH_UNBALANCED_OR_SOURCE_MISMATCH', $e->getMessage());
        }
        $this->expectException(\DomainException::class);
        (new V2CashPostingWriter())->post(2, 42, 'CASH-REQUEST-000004', $this->crLegs());
    }

    public function test_CP_source_posting_and_replay_tampering_detection(): void
    {
        $legs = [
            ['account_id' => 102, 'debit' => '80', 'credit' => '0'],
            ['account_id' => 101, 'debit' => '0', 'credit' => '80'],
        ];
        $writer = new V2CashPostingWriter();
        $id = $writer->post(1, 43, 'CASH-REQUEST-000005', $legs);
        DB::table('finance_posting_lines')
            ->where('posting_batch_id', $id)
            ->where('project_head_subhead_id', 102)
            ->update(['debit' => '81.00']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_CASH_REPLAY_LINES_TAMPERED');
        $writer->post(1, 43, 'CASH-REQUEST-000005', $legs);
    }

    public function test_different_key_cannot_repost_same_cash_source(): void
    {
        $writer = new V2CashPostingWriter();
        $writer->post(1, 42, 'CASH-REQUEST-000006', $this->crLegs());
        try {
            $writer->post(1, 42, 'CASH-REQUEST-000007', $this->crLegs());
            $this->fail('Expected physical source unique FK rejection');
        } catch (QueryException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_batches', 1);
        $this->assertDatabaseCount('finance_posting_lines', 2);
    }
    public function test_cash_account_id_coercion_is_rejected_before_posting(): void
    {
        foreach (['101junk', '101.0', '00101', 101.5, false] as $index => $invalid) {
            $legs = $this->crLegs();
            $legs[0]['account_id'] = $invalid;
            try {
                (new V2CashPostingWriter())->post(
                    1, 42, 'CASH-STRICT-ID-' . str_pad((string) $index, 5, '0', STR_PAD_LEFT),
                    $legs
                );
                $this->fail('Coerced account ID was accepted');
            } catch (\DomainException $e) {
                $this->assertSame('V2_CASH_ACCOUNT_ID_INVALID', $e->getMessage());
            }
        }
        $this->assertDatabaseCount('finance_posting_batches', 0);
        $this->assertDatabaseCount('finance_posting_lines', 0);
    }

    public function test_cash_replay_rejects_mutated_batch_series(): void
    {
        $writer = new V2CashPostingWriter();
        $id = $writer->post(1, 42, 'CASH-FAMILY-REPLAY-0001', $this->crLegs());
        DB::table('finance_posting_batches')
            ->where('id', $id)->update(['voucher_series' => 'CP']);
        try {
            $writer->post(1, 42, 'CASH-FAMILY-REPLAY-0001', $this->crLegs());
            $this->fail('Expected corrupted batch voucher series rejection');
        } catch (\DomainException $e) {
            $this->assertSame('V2_CASH_IDEMPOTENCY_CONFLICT', $e->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_batches', 1);
    }

    public function test_cash_replay_rejects_tampered_effective_date(): void
    {
        $writer = new V2CashPostingWriter();
        $id = $writer->post(1, 42, 'CASH-HARDEN-DATE-0001', $this->crLegs());
        DB::table('finance_posting_batches')->where('id', $id)
            ->update(['effective_date' => '2026-10-31']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_CASH_IDEMPOTENCY_CONFLICT');
        $writer->post(1, 42, 'CASH-HARDEN-DATE-0001', $this->crLegs());
    }

    public function test_cash_receipt_reversal_preserves_original_and_typed_source_identity(): void
    {
        $batch = (new V2CashPostingWriter())->post(1, 42, 'CASH-REVERSAL-ORIGINAL-01', $this->crLegs());
        $reversal = (new V2PostingReversalWriter())->reverse(
            1, $batch, 'CASH-REVERSAL-REQUEST-01', '2026-10-31', 'Approved correction'
        );
        $this->assertDatabaseCount('finance_posting_batches', 2);
        $this->assertDatabaseCount('finance_posting_lines', 4);
        $this->assertDatabaseCount('finance_posting_cash_sources', 1);
        $this->assertDatabaseCount('finance_posting_source_claims', 1);
        $rows = (new V2PostingReportReader())->lines(1, '2026-10-31', '2026-10-31');
        $this->assertCount(2, $rows);
        $this->assertTrue($rows[0]['is_reversal']);
        $this->assertSame('cash', $rows[0]['source_kind']);
        $this->assertSame(42, $rows[0]['source_id']);
        $this->assertSame($batch, (int) $rows[0]['reversal_of_batch_id']);
        $this->assertSame('250.00', (string) DB::table('finance_posting_lines')
            ->where('posting_batch_id', $batch)->where('project_head_subhead_id', 101)
            ->value('debit'));
        $this->assertSame('250.00', (string) DB::table('finance_posting_lines')
            ->where('posting_batch_id', $reversal)->where('project_head_subhead_id', 101)
            ->value('credit'));
    }

    public function test_cash_payment_reversal_replay_and_duplicate_source_reversal_are_guarded(): void
    {
        $legs = [
            ['account_id' => 102, 'debit' => '80.00', 'credit' => '0.00'],
            ['account_id' => 101, 'debit' => '0.00', 'credit' => '80.00'],
        ];
        $batch = (new V2CashPostingWriter())->post(
            1, 43, 'CASH-REVERSAL-ORIGINAL-02', $legs
        );
        $writer = new V2PostingReversalWriter();
        $id = $writer->reverse(1, $batch, 'CASH-REVERSAL-REQUEST-02', '2026-10-31', 'Correction');
        $this->assertSame($id, $writer->reverse(
            1, $batch, 'CASH-REVERSAL-REQUEST-02', '2026-10-31', 'Correction'
        ));
        try {
            $writer->reverse(1, $batch, 'CASH-REVERSAL-REQUEST-03', '2026-10-31', 'Correction');
            $this->fail('A second reversal was accepted');
        } catch (\DomainException $e) {
            $this->assertSame('V2_REVERSAL_ALREADY_EXISTS', $e->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_batches', 2);
    }

    public function test_cash_reversal_wrong_project_refuses_without_mutation(): void
    {
        $batch = (new V2CashPostingWriter())->post(
            1, 42, 'CASH-REVERSAL-ORIGINAL-04', $this->crLegs()
        );
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('V2_REVERSAL_ORIGINAL_NOT_POSTED');
        try {
            (new V2PostingReversalWriter())->reverse(
                2, $batch, 'CASH-REVERSAL-REQUEST-04', '2026-10-31', 'Correction'
            );
        } finally {
            $this->assertDatabaseCount('finance_posting_batches', 1);
        }
    }

}
