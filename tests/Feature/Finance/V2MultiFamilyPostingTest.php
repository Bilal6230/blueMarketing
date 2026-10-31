<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\V2PostingGateway;
use App\Services\Finance\V2StockSourceSnapshot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * DEFERRED regression definitions. Do not treat these as executed/passing.
 * All objects are isolated in SQLite :memory: and cannot touch real DB.
 */
final class V2MultiFamilyPostingTest extends TestCase
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

        foreach (['projects', 'users', 'journal_vouchers', 'journal_voucher_details'] as $name) {
            Schema::create($name, function (Blueprint $t): void {
                $t->id();
            });
        }
        Schema::create('project_head_subheads', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('project_id');
        });
        Schema::create('commision_vouchers', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->string('voucher_number');
            $t->date('date');
            $t->string('status');
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->decimal('total_debit', 15, 2);
            $t->decimal('total_credit', 15, 2);
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('commision_voucher_details', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('commision_voucher_id');
            $t->unsignedBigInteger('account_id');
            $t->decimal('debit', 15, 2);
            $t->decimal('credit', 15, 2);
            $t->text('description')->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('customer_ledger', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->string('transaction_type');
            $t->decimal('amount_in', 10, 2);
            $t->decimal('amount_out', 10, 2);
            $t->unsignedBigInteger('type_id')->nullable();
            $t->date('date');
            $t->unsignedTinyInteger('is_active')->default(1);
            $t->unsignedTinyInteger('is_approve')->default(1);
        });
        Schema::create('booking_vouchers', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->decimal('amount', 12, 2);
            $t->date('receipt_date');
            $t->string('voucher_series');
            $t->unsignedBigInteger('voucher_number')->nullable();
            $t->unsignedBigInteger('customer_ledger_id')->nullable();
            $t->unsignedBigInteger('ledger_id')->nullable();
            $t->unsignedTinyInteger('is_active')->default(1);
            $t->unsignedTinyInteger('is_approve')->default(1);
        });
        Schema::create('labours', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('project_id');
        });
        Schema::create('labour_ledgers', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('labour_id');
            $t->decimal('amount', 10, 2);
            $t->date('date');
            $t->text('details')->nullable();
        });
        Schema::create('stock_entries', function (Blueprint $t): void {
            $t->id();
            $t->date('entry_date');
            $t->string('flow');
            $t->string('slip_no');
            $t->unsignedBigInteger('party_id');
            $t->timestamp('billed_at')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('stock_entry_lines', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('entry_id');
            $t->unsignedBigInteger('item_id');
            $t->decimal('qty', 18, 3);
            $t->unsignedBigInteger('rate_minor');
            $t->unsignedBigInteger('amount_minor');
        });
        Schema::create('stock_bills', function (Blueprint $t): void {
            $t->id();
            $t->date('bill_date');
            $t->string('type');
            $t->string('bill_no');
            $t->unsignedBigInteger('party_id');
            $t->unsignedBigInteger('entry_id')->nullable();
            $t->unsignedBigInteger('total_minor');
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('stock_bill_lines', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('bill_id');
            $t->unsignedBigInteger('item_id');
            $t->decimal('qty', 18, 3);
            $t->unsignedBigInteger('rate_minor');
            $t->unsignedBigInteger('amount_minor');
        });

        (require base_path('database/v2_migrations/2026_10_30_000100_create_v2_finance_posting_core.php'))->up();
        (require base_path('database/v2_migrations/2026_10_31_000110_create_v2_commission_detail_links.php'))->up();
        (require base_path('database/v2_migrations/2026_10_31_000120_create_v2_stock_project_approvals.php'))->up();

        DB::table('projects')->insert([['id' => 1], ['id' => 2]]);
        DB::table('users')->insert(['id' => 1]);
        DB::table('project_head_subheads')->insert([
            ['id' => 101, 'project_id' => 1],
            ['id' => 102, 'project_id' => 1],
            ['id' => 201, 'project_id' => 2],
        ]);
        DB::table('commision_vouchers')->insert([
            'id' => 10, 'project_id' => 1, 'voucher_number' => 'CV-10-A',
            'date' => '2026-10-30', 'status' => 'approved',
            'total_debit' => '90.00', 'total_credit' => '90.00',
        ]);
        DB::table('commision_voucher_details')->insert([
            ['id' => 11, 'commision_voucher_id' => 10, 'account_id' => 101,
                'debit' => '90.00', 'credit' => '0.00', 'description' => 'CV debit'],
            ['id' => 12, 'commision_voucher_id' => 10, 'account_id' => 102,
                'debit' => '0.00', 'credit' => '90.00', 'description' => 'CV credit'],
        ]);
        foreach (['PPR', 'EC', 'ECP'] as $offset => $type) {
            DB::table('customer_ledger')->insert([
                'id' => 20 + $offset, 'project_id' => 1,
                'transaction_type' => $type, 'amount_in' => '25.00',
                'amount_out' => '0.00', 'type_id' => 777,
                'date' => '2026-10-30', 'is_active' => 1, 'is_approve' => 1,
            ]);
        }
        DB::table('booking_vouchers')->insert([
            'id' => 30, 'project_id' => 1, 'amount' => '40.00',
            'receipt_date' => '2026-10-30', 'voucher_series' => 'BR',
            'voucher_number' => 8, 'is_active' => 1, 'is_approve' => 1,
        ]);
        DB::table('labours')->insert(['id' => 1, 'project_id' => 1]);
        DB::table('labour_ledgers')->insert([
            'id' => 40, 'project_id' => 1, 'labour_id' => 1,
            'amount' => '55.00', 'date' => '2026-10-30',
        ]);
        DB::table('stock_entries')->insert([
            'id' => 50, 'entry_date' => '2026-10-30',
            'flow' => 'IN', 'slip_no' => 'ST-50', 'party_id' => 2,
        ]);
        DB::table('stock_entry_lines')->insert([
            'id' => 501, 'entry_id' => 50, 'item_id' => 7,
            'qty' => '1.000', 'rate_minor' => 7000, 'amount_minor' => 7000,
        ]);
        DB::table('stock_bills')->insert([
            'id' => 60, 'bill_date' => '2026-10-30',
            'type' => 'purchase', 'bill_no' => 'SB-60',
            'party_id' => 2, 'total_minor' => 8000,
        ]);
        DB::table('stock_bill_lines')->insert([
            'id' => 601, 'bill_id' => 60, 'item_id' => 7,
            'qty' => '1.000', 'rate_minor' => 8000, 'amount_minor' => 8000,
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function legs(string $amount): array
    {
        return [
            ['account_id' => 101, 'debit' => $amount, 'credit' => '0.00'],
            ['account_id' => 102, 'debit' => '0.00', 'credit' => $amount],
        ];
    }

    public function test_isolated_cv_draft_approval_posts_atomically_and_replays(): void
    {
        $workflow = new \App\Services\Finance\V2CommissionDraftWorkflow();
        $id = $workflow->createDraft(1, 'CV-NEW-ISOLATED-42', '2026-10-30', $this->legs('40.00'), 1);
        $this->assertDatabaseHas('commision_vouchers', ['id' => $id, 'status' => 'draft']);
        $this->assertDatabaseCount('finance_posting_batches', 0);

        $batch = $workflow->approveAndPost(1, $id, 'CV-DRAFT-APPROVAL-POST-001', 1);
        $this->assertSame($batch, $workflow->approveAndPost(
            1, $id, 'CV-DRAFT-APPROVAL-POST-001', 1
        ));
        $this->assertDatabaseHas('commision_vouchers', ['id' => $id, 'status' => 'approved']);
        $this->assertDatabaseHas('finance_posting_commission_sources', [
            'posting_batch_id' => $batch, 'commision_voucher_id' => $id,
        ]);
        $this->assertDatabaseCount('finance_posting_batches', 1);
    }

    public function test_isolated_cv_draft_rejects_cross_project_accounts_without_writes(): void
    {
        $workflow = new \App\Services\Finance\V2CommissionDraftWorkflow();
        try {
            $workflow->createDraft(1, 'CV-FOREIGN-DENIED', '2026-10-30', [
                ['account_id' => 101, 'debit' => '40.00', 'credit' => '0.00'],
                ['account_id' => 201, 'debit' => '0.00', 'credit' => '40.00'],
            ]);
            $this->fail('Cross-project account must not create CV');
        } catch (\DomainException $e) {
            $this->assertDatabaseMissing('commision_vouchers', [
                'voucher_number' => 'CV-FOREIGN-DENIED',
            ]);
        }
    }

    public function test_commission_posts_exact_document_and_detail_fks_idempotently(): void
    {
        $gateway = new V2PostingGateway();
        $id = $gateway->postCommission(1, 10, 'CV-REQUEST-EXACT-0001');
        $this->assertSame($id, $gateway->postCommission(1, 10, 'CV-REQUEST-EXACT-0001'));
        $this->assertDatabaseCount('finance_commission_line_sources', 2);
        $this->assertDatabaseHas('finance_posting_commission_sources', [
            'posting_batch_id' => $id, 'commision_voucher_id' => 10,
        ]);
        $this->assertNull(DB::table('finance_posting_batches')
            ->where('id', $id)->value('voucher_number'));
        $this->assertSame('commission', $gateway->postingLines(1)[0]['source_kind']);
        $this->assertSame(10, $gateway->postingLines(1)[0]['source_id']);
    }

    public function test_posted_commission_void_by_exact_source_is_immutable_and_idempotent(): void
    {
        $gateway = new V2PostingGateway();
        $original = $gateway->postCommission(1, 10, 'CV-VOID-ORIGINAL-0001');
        $lifecycle = new \App\Services\Finance\V2VoucherLifecycleService();

        $reversal = $lifecycle->reverse(
            1, 'commission', 10, 'CV-VOID-REVERSAL-0001',
            '2026-10-31', 'Approved commission correction'
        );
        $this->assertNotSame($original, $reversal);
        $this->assertSame($reversal, $lifecycle->reverse(
            1, 'commission', 10, 'CV-VOID-REVERSAL-0001',
            '2026-10-31', 'Approved commission correction'
        ));
        $this->assertDatabaseHas('finance_posting_batches', [
            'id' => $reversal, 'reversal_of_batch_id' => $original,
        ]);
        $this->assertDatabaseHas('commision_vouchers', [
            'id' => 10, 'project_id' => 1, 'status' => 'approved',
        ]);
        $this->assertDatabaseCount('finance_posting_batches', 2);
        $this->assertDatabaseCount('finance_posting_lines', 4);

        try {
            $lifecycle->reverse(
                1, 'commission', 10, 'CV-VOID-SECOND-KEY-0001',
                '2026-10-31', 'Other reversal'
            );
            $this->fail('Reversing the same commission twice must be rejected');
        } catch (\DomainException $exception) {
            $this->assertDatabaseCount('finance_posting_batches', 2);
        }
    }

    public function test_commission_void_wrong_project_cannot_reverse_original_batch(): void
    {
        $gateway = new V2PostingGateway();
        $original = $gateway->postCommission(1, 10, 'CV-PROJECT-SOURCE-0001');
        $lifecycle = new \App\Services\Finance\V2VoucherLifecycleService();
        try {
            $lifecycle->reverse(
                2, 'commission', 10, 'CV-PROJECT-VOID-BLOCK-0001',
                '2026-10-31', 'Wrong project'
            );
            $this->fail('Cross-project source void must be rejected');
        } catch (\DomainException $exception) {
            $this->assertDatabaseCount('finance_posting_batches', 1);
            $this->assertDatabaseHas('finance_posting_batches', [
                'id' => $original, 'reversal_of_batch_id' => null,
            ]);
        }
    }

    public function test_commission_rejects_changed_detail_replay(): void
    {
        $gateway = new V2PostingGateway();
        $gateway->postCommission(1, 10, 'CV-REQUEST-TAMPER-0001');
        DB::table('finance_posting_lines')->where('project_head_subhead_id', 101)
            ->update(['debit' => '99.00']);
        $this->expectException(\DomainException::class);
        $gateway->postCommission(1, 10, 'CV-REQUEST-TAMPER-0001');
    }

    public function test_explicit_customer_ppr_ec_ecp_types_have_distinct_exact_sources(): void
    {
        $gateway = new V2PostingGateway();
        foreach (['PPR', 'EC', 'ECP'] as $offset => $type) {
            $id = $gateway->postCustomerVoucher(
                1, 20 + $offset, $type, 'CUSTOMER-EXPLICIT-' . $type . '-0001',
                $this->legs('25.00')
            );
            $this->assertNull(DB::table('finance_posting_batches')
                ->where('id', $id)->value('voucher_number'));
        }
        $this->assertDatabaseCount('finance_posting_cash_sources', 3);
        $this->assertDatabaseCount('finance_posting_source_claims', 3);
        $this->assertCount(6, $gateway->postingLines(1));
    }

    public function test_wrong_expected_customer_type_cannot_post(): void
    {
        $this->expectException(\DomainException::class);
        (new V2PostingGateway())->postCustomerVoucher(
            1, 20, 'EC', 'CUSTOMER-WRONG-TYPE-0001', $this->legs('25.00')
        );
    }

    public function test_booking_and_labour_post_from_physical_project_fks(): void
    {
        $gateway = new V2PostingGateway();
        $gateway->postBooking(1, 30, 'BOOKING-POSTING-0001', $this->legs('40.00'));
        $gateway->postLabour(1, 40, 'LABOUR-POSTING-0001', $this->legs('55.00'));
        $this->assertDatabaseCount('finance_posting_booking_sources', 1);
        $this->assertDatabaseCount('finance_posting_labour_sources', 1);
        $this->assertCount(4, $gateway->postingLines(1));
        $this->assertSame('95.00', $gateway->postingSummary(1)['total_debit']);
    }

    public function test_booking_cross_project_posting_is_denied(): void
    {
        $this->expectException(\DomainException::class);
        (new V2PostingGateway())->postBooking(
            2, 30, 'BOOKING-FOREIGN-PROJECT-0001', $this->legs('40.00')
        );
    }

    public function test_stock_requires_exact_project_approval_snapshot(): void
    {
        $gateway = new V2PostingGateway();
        try {
            $gateway->postStockEntry(1, 50, 'STOCK-UNAPPROVED-0001', $this->legs('70.00'));
            $this->fail('Stock posted without a project ownership approval');
        } catch (\DomainException $e) {
            $this->assertSame('V2_STOCK_PROJECT_APPROVAL_MISSING_OR_STALE', $e->getMessage());
        }
        $snapshot = (new V2StockSourceSnapshot())->read('stock_entry', 50);
        DB::table('finance_stock_entry_project_approvals')->insert([
            'stock_entry_id' => 50, 'project_id' => 1,
            'approved_by' => 1, 'evidence_reference' => 'OWNER-APPROVED-50',
            'source_sha256' => $snapshot['fingerprint'],
            'approved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = $gateway->postStockEntry(1, 50, 'STOCK-APPROVED-0001', $this->legs('70.00'));
        $this->assertDatabaseHas('finance_posting_stock_entry_sources', [
            'posting_batch_id' => $id, 'stock_entry_id' => 50,
        ]);
        $this->assertSame($id, $gateway->postStockEntry(
            1, 50, 'STOCK-APPROVED-0001', $this->legs('70.00')
        ));
    }

    public function test_stock_bill_posts_only_with_independent_evidence_mapping(): void
    {
        $snapshot = (new V2StockSourceSnapshot())->read('stock_bill', 60);
        DB::table('finance_stock_bill_project_approvals')->insert([
            'stock_bill_id' => 60, 'project_id' => 1,
            'approved_by' => 1, 'evidence_reference' => 'OWNER-APPROVED-60',
            'source_sha256' => $snapshot['fingerprint'],
            'approved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $gateway = new V2PostingGateway();
        $id = $gateway->postStockBill(1, 60, 'STOCK-BILL-POST-0001', $this->legs('80.00'));
        $this->assertDatabaseHas('finance_posting_stock_bill_sources', [
            'posting_batch_id' => $id, 'stock_bill_id' => 60,
        ]);
        $this->assertSame('80.00', $gateway->postingSummary(1)['total_credit']);
    }

    public function test_summary_keeps_original_source_and_reversal_separate(): void
    {
        $gateway = new V2PostingGateway();
        $source = $gateway->postCommission(1, 10, 'CV-SUMMARY-ORIGINAL-01');
        $gateway->reversePostedBatch(1, $source, 'CV-SUMMARY-REVERSE-01',
            '2026-10-31', 'Correction');
        $summary = $gateway->postingSummary(1);
        $this->assertSame(2, $summary['posting_batch_count']);
        $this->assertSame('180.00', $summary['total_debit']);
        $this->assertSame('180.00', $summary['total_credit']);
        $this->assertCount(2, $summary['groups']);
    }
    public function test_voucher_register_shows_real_cv_text_reference_not_legacy_type_id(): void
    {
        $gateway = new V2PostingGateway();
        $batch = $gateway->postCommission(1, 10, 'CV-TEXT-REGISTER-0001');
        $documents = $gateway->voucherRegister(1);
        $this->assertCount(1, $documents);
        $this->assertSame($batch, $documents[0]['posting_batch_id']);
        $this->assertSame('CV-10-A', $documents[0]['source_reference']);
        $this->assertSame('commission', $documents[0]['source_kind']);
        $this->assertSame(10, $documents[0]['source_id']);
        $this->assertSame('90.00', $documents[0]['total_debit']);
        $this->assertSame('90.00', $documents[0]['total_credit']);
    }

}
