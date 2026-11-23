<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\PlotEntryWriter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlotEntryWriterTest extends TestCase
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
            'stock_entries', 'stock_bills', 'customer_ledger', 'booking_vouchers', 'labour_ledgers',
        ] as $name) Schema::create($name, fn (Blueprint $t) => $t->id());
        // Full typed posting needs 1:1 authentic general-ledger evidence.
        // These SQLite fixtures intentionally mirror the writer contract.
        // PPR numbering reconciles the original booking receipt series too.
        // The placeholder booking_vouchers table above must expose its real
        // number columns even when this fixture has no legacy receipts.
        Schema::table('booking_vouchers', function (Blueprint $t): void {
            $t->unsignedBigInteger('project_id')->nullable();
            $t->string('voucher_series')->nullable();
            $t->unsignedBigInteger('voucher_number')->nullable();
        });
        Schema::create('ledgers', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('customer_ledger_id')->nullable();
            $t->unsignedBigInteger('project_head_subheads_id');
            $t->string('type',12); $t->unsignedBigInteger('type_id');
            $t->unsignedBigInteger('voucher_number')->nullable();
            $t->string('reference')->nullable();
            $t->decimal('amount_in',18,2)->default(0);
            $t->decimal('amount_out',18,2)->default(0);
            $t->text('detail')->nullable();
            $t->unsignedBigInteger('create_by')->nullable();
            $t->date('date')->nullable(); $t->boolean('is_active')->default(true);
            $t->string('status')->default('0'); $t->timestamps();
        });
        Schema::create('ledger_source_attributions', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('ledger_id')->unique();
            $t->unsignedBigInteger('project_id'); $t->string('source_kind');
            $t->unsignedBigInteger('source_id'); $t->string('evidence_kind');
            $t->string('evidence_reference')->nullable();
            $t->unsignedBigInteger('attributed_by')->nullable(); $t->timestamps();
        });
        Schema::create('leads', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('first_name')->nullable(); $t->string('last_name')->nullable(); $t->string('business')->nullable();
        });
        Schema::create('roads', function (Blueprint $t): void { $t->id(); $t->boolean('is_active'); });
        Schema::create('facings', function (Blueprint $t): void { $t->id(); $t->boolean('is_active'); });
        Schema::create('plots', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->string('name'); $t->string('type');
            $t->string('size'); $t->string('unit'); $t->text('description')->nullable(); $t->integer('is_corner')->default(0);
            $t->unsignedBigInteger('road_id'); $t->unsignedBigInteger('facing_id'); $t->integer('is_active');
            $t->unsignedBigInteger('create_by')->nullable(); $t->integer('sold')->default(0); $t->decimal('amount',18,2)->default(0);
            $t->timestamps();
        });
        Schema::create('bookings', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('customer_id');
            $t->unsignedBigInteger('plot_id'); $t->string('status'); $t->string('cancel_status');
        });
        Schema::create('head_accountings', function (Blueprint $t): void { $t->id(); $t->boolean('is_active')->default(true); });
        Schema::create('subhead_accountings', function (Blueprint $t): void { $t->id(); $t->boolean('is_active')->default(true); });
        Schema::create('project_head_subheads', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('head_accounting_id');
            $t->unsignedBigInteger('subhead_accounting_id'); $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('plot_id')->nullable(); $t->unique(['id', 'project_id']);
        });
        (require base_path('database/finance_legacy_migrations/2026_10_30_000100_create_finance_finance_posting_core.php'))->up();
        (require base_path('database/finance_legacy_migrations/2026_11_05_000300_create_finance_plot_receipt_purchase.php'))->up();
        (require base_path('database/migrations/2026_11_18_190000_add_plot_detail_lifecycle_columns.php'))->up();
        DB::table('projects')->insert([['id' => 1], ['id' => 2]]);
        DB::table('users')->insert(['id' => 7]);
        DB::table('leads')->insert([
            ['id' => 5, 'project_id' => 1, 'first_name' => 'A', 'last_name' => 'Customer', 'business' => null],
            ['id' => 6, 'project_id' => 2, 'first_name' => 'B', 'last_name' => 'Customer', 'business' => null],
        ]);
        DB::table('roads')->insert(['id' => 1, 'is_active' => 1]);
        DB::table('facings')->insert(['id' => 1, 'is_active' => 1]);
        DB::table('plots')->insert([
            ['id' => 11, 'project_id' => 1, 'name' => 'A-11', 'type' => '1', 'size' => '1', 'unit' => 'Kanal', 'description' => '', 'road_id' => 1, 'facing_id' => 1, 'is_active' => 1],
            ['id' => 12, 'project_id' => 1, 'name' => 'A-12', 'type' => '1', 'size' => '1', 'unit' => 'Kanal', 'description' => '', 'road_id' => 1, 'facing_id' => 1, 'is_active' => 1],
            ['id' => 21, 'project_id' => 2, 'name' => 'B-21', 'type' => '1', 'size' => '1', 'unit' => 'Kanal', 'description' => '', 'road_id' => 1, 'facing_id' => 1, 'is_active' => 1],
        ]);
        DB::table('bookings')->insert([
            ['id' => 31, 'project_id' => 1, 'customer_id' => 5, 'plot_id' => 11, 'status' => 'active', 'cancel_status' => '0'],
            ['id' => 32, 'project_id' => 1, 'customer_id' => 5, 'plot_id' => 12, 'status' => 'active', 'cancel_status' => '0'],
            ['id' => 41, 'project_id' => 2, 'customer_id' => 6, 'plot_id' => 21, 'status' => 'active', 'cancel_status' => '0'],
        ]);
        DB::table('head_accountings')->insert([['id' => 10], ['id' => 20]]);
        DB::table('subhead_accountings')->insert([['id' => 10], ['id' => 20]]);
        DB::table('project_head_subheads')->insert([
            ['id' => 101, 'project_id' => 1, 'head_accounting_id' => 10, 'subhead_accounting_id' => 10, 'customer_id' => null, 'plot_id' => null],
            ['id' => 102, 'project_id' => 1, 'head_accounting_id' => 20, 'subhead_accounting_id' => 20, 'customer_id' => 5, 'plot_id' => 11],
            ['id' => 103, 'project_id' => 1, 'head_accounting_id' => 20, 'subhead_accounting_id' => 20, 'customer_id' => 5, 'plot_id' => 12],
            ['id' => 104, 'project_id' => 1, 'head_accounting_id' => 10, 'subhead_accounting_id' => 10, 'customer_id' => null, 'plot_id' => null],
            ['id' => 201, 'project_id' => 2, 'head_accounting_id' => 10, 'subhead_accounting_id' => 10, 'customer_id' => null, 'plot_id' => null],
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite'); parent::tearDown();
    }

    public function test_multi_plot_receipt_posts_once_and_exposes_separate_plot_allocations(): void
    {
        $writer = new PlotEntryWriter();
        $data = ['date' => '2026-11-05', 'receipt_number' => 'PPR-1', 'amount' => '100.00',
            'cash_account_id' => 101, 'narration' => 'Two plot payment', 'allocations' => [
                ['booking_id' => 31, 'plot_id' => 11, 'receivable_account_id' => 102, 'amount' => '60.00'],
                ['booking_id' => 32, 'plot_id' => 12, 'receivable_account_id' => 103, 'amount' => '40.00'],
            ]];
        $created = $writer->receive(1, 5, 'PLOT-RECEIPT-REQUEST-0001', $data, 7);
        $replayed = $writer->receive(1, 5, 'PLOT-RECEIPT-REQUEST-0001', $data, 7);
        $this->assertFalse($created['replayed']); $this->assertTrue($replayed['replayed']);
        $this->assertSame($created['source_id'], $replayed['source_id']);
        $this->assertDatabaseCount('finance_plot_receipts', 1);
        $this->assertSame(0,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$created['posting_batch_id'])
            ->whereNull('legacy_ledger_id')->count());
        $this->assertSame(3,DB::table('ledger_source_attributions')->count());

        $this->assertDatabaseCount('finance_plot_receipt_allocations', 2);
        $this->assertSame(100.0, (float) DB::table('finance_posting_lines')->where('posting_batch_id', $created['posting_batch_id'])->where('project_head_subhead_id', 101)->value('debit'));
        $details = $writer->receipt(1, $created['source_id']);
        $this->assertSame(['A-11', 'A-12'], $details['allocations']->pluck('plot_name')->all());
        $register = (new \App\Services\Finance\PostingGateway())->voucherRegister(1);
        $this->assertSame('PPR-1', collect($register)->firstWhere('source_kind', 'plot_receipt')['source_reference']);
    }

    public function test_single_plot_receipt_posts_balanced_and_details_are_project_scoped(): void
    {
        $writer = new PlotEntryWriter();
        $payload = [
            'date' => '2026-11-14',
            'receipt_number' => 'PPR-1',
            'amount' => '75.50',
            'cash_account_id' => 101,
            'narration' => 'Single plot payment',
            'allocations' => [[
                'booking_id' => 31, 'plot_id' => 11,
                'receivable_account_id' => 102, 'amount' => '75.50',
            ]],
        ];
        $created = $writer->receive(1, 5, 'PLOT-SINGLE-REQUEST-0001', $payload, 7);
        $this->assertFalse($created['replayed']);
        $this->assertDatabaseCount('finance_plot_receipts', 1);
        $this->assertDatabaseCount('finance_plot_receipt_allocations', 1);
        $lines = DB::table('finance_posting_lines')->where('posting_batch_id', $created['posting_batch_id'])->get();
        $this->assertCount(2, $lines);
        $this->assertSame(75.5, (float) $lines->sum('debit'));
        $this->assertSame(75.5, (float) $lines->sum('credit'));
        $details = $writer->receipt(1, $created['source_id']);
        $this->assertCount(1, $details['allocations']);
        $this->assertSame('A-11', $details['allocations'][0]->plot_name);
        $this->assertSame(31, (int) $details['allocations'][0]->booking_id);
        $this->assertSame(5, (int) $details['receipt']->customer_id);
        try {
            $writer->receipt(2, $created['source_id']);
            $this->fail('Another project may not open this receipt.');
        } catch (\DomainException $exception) {
            $this->assertSame('V2_PLOT_RECEIPT_NOT_FOUND', $exception->getMessage());
        }
        $replay = $writer->receive(1, 5, 'PLOT-SINGLE-REQUEST-0001', $payload, 7);
        $this->assertTrue($replay['replayed']);
        $this->assertDatabaseCount('finance_posting_batches', 1);
    }

    public function test_zero_plot_allocations_rejected_without_financial_posting(): void
    {
        $writer = new PlotEntryWriter();
        try {
            $writer->receive(1, 5, 'PLOT-ZERO-REQUEST-00001', [
                'date' => '2026-11-14', 'receipt_number' => 'PPR-1',
                'amount' => '100.00', 'cash_account_id' => 101,
                'narration' => 'Empty allocation', 'allocations' => [],
            ], 7);
            $this->fail('Receipt with no plot allocations should fail.');
        } catch (\DomainException $exception) {
            $this->assertSame('V2_PLOT_RECEIPT_REQUIRES_ALLOCATION', $exception->getMessage());
        }
        $this->assertDatabaseCount('finance_plot_receipts', 0);
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_plot_purchase_posts_inventory_cost_to_seller_and_is_idempotent(): void
    {
        $writer = new PlotEntryWriter();
        $data = ['date' => '2026-11-05', 'purchase_number' => 'PP-1', 'seller_account_id' => 102,
            'narration' => 'Land purchase', 'items' => [
                ['plot_code' => 'LAND-1', 'description' => 'Parcel one', 'area' => '1.2500', 'unit' => 'Kanal', 'type' => 1, 'road_id' => 1, 'facing_id' => 1, 'is_corner' => false, 'unit_rate' => '100.00', 'inventory_account_id' => 104],
            ]];
        $created = $writer->purchase(1, 'PLOT-PURCHASE-REQUEST-0001', $data, 7);
        $again = $writer->purchase(1, 'PLOT-PURCHASE-REQUEST-0001', $data, 7);
        $this->assertFalse($created['replayed']); $this->assertTrue($again['replayed']);
        $lines = DB::table('finance_posting_lines')->where('posting_batch_id', $created['posting_batch_id'])->get()->keyBy('project_head_subhead_id');
        $this->assertSame(125.0, (float) $lines[104]->debit);
        $this->assertSame(125.0, (float) $lines[102]->credit);
        $this->assertSame('PP-1', collect((new \App\Services\Finance\PostingGateway())->voucherRegister(1))
            ->firstWhere('source_kind', 'plot_purchase')['source_reference']);
        $this->assertEquals(1.25, (float) $writer->purchaseDetails(1, $created['source_id'])['items'][0]->area);
        $legacyPlotId = (int) $writer->purchaseDetails(1, $created['source_id'])['items'][0]->legacy_plot_id;
        $this->assertSame(1, (int) DB::table('plots')->where('id', $legacyPlotId)->value('is_active'));
        $this->assertSame(0, (int) DB::table('plots')->where('id', $legacyPlotId)->value('sold'));
        $this->assertSame(0.0, (float) DB::table('plots')->where('id', $legacyPlotId)->value('amount'));
    }

    public function test_foreign_project_plot_booking_and_unallocated_amounts_fail_without_writes(): void
    {
        $writer = new PlotEntryWriter();
        $data = ['date' => '2026-11-05', 'receipt_number' => 'PPR-1', 'amount' => '100.00', 'cash_account_id' => 101,
            'narration' => 'Invalid receipt', 'allocations' => [
                ['booking_id' => 31, 'plot_id' => 11, 'receivable_account_id' => 102, 'amount' => '60'],
                ['booking_id' => 41, 'plot_id' => 21, 'receivable_account_id' => 103, 'amount' => '40'],
            ]];
        try { $writer->receive(1, 5, 'PLOT-RECEIPT-REQUEST-0002', $data, 7); $this->fail('Cross-project plot booking must be rejected'); }
        catch (\DomainException $e) { $this->assertSame('V2_PLOT_BOOKING_PROJECT_MISMATCH', $e->getMessage()); }
        $data['allocations'][1]['booking_id'] = 32; $data['allocations'][1]['plot_id'] = 12;
        $data['allocations'][1]['amount'] = '39';
        try { $writer->receive(1, 5, 'PLOT-RECEIPT-REQUEST-0003', $data, 7); $this->fail('Allocation total mismatch must be rejected'); }
        catch (\DomainException $e) { $this->assertSame('V2_PLOT_ALLOCATION_TOTAL_MISMATCH', $e->getMessage()); }
        $this->assertDatabaseCount('finance_plot_receipts', 0);
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_multi_plot_receipt_rejects_duplicate_plot_allocations_without_writes(): void
    {
        $data = ['date' => '2026-11-05', 'receipt_number' => 'PPR-1', 'amount' => '100.00', 'cash_account_id' => 101,
            'narration' => 'Duplicate plot must not post', 'allocations' => [
                ['booking_id' => 31, 'plot_id' => 11, 'receivable_account_id' => 102, 'amount' => '60.00'],
                ['booking_id' => 31, 'plot_id' => 11, 'receivable_account_id' => 102, 'amount' => '40.00'],
            ]];
        try {
            (new PlotEntryWriter())->receive(1, 5, 'PLOT-RECEIPT-DUPLICATE-PLOT', $data, 7);
            $this->fail('A single plot cannot appear twice in a multi-plot receipt.');
        } catch (\DomainException $error) {
            $this->assertSame('V2_PLOT_ALLOCATION_INVALID', $error->getMessage());
        }
        $this->assertDatabaseCount('finance_plot_receipts', 0);
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_plot_purchase_cancellation_is_append_only_and_reverses_exact_posting(): void
    {
        $writer = new PlotEntryWriter();
        $created = $writer->purchase(1, 'PLOT-PURCHASE-REQUEST-REV1', [
            'date' => '2026-11-05', 'purchase_number' => 'PP-1', 'seller_account_id' => 102,
            'narration' => 'Reversible purchase', 'items' => [[
                'plot_code' => 'LAND-R1', 'description' => 'Parcel reversal test', 'area' => '1',
                'unit' => 'Kanal', 'type' => 1, 'road_id' => 1, 'facing_id' => 1,
                'unit_rate' => '100', 'inventory_account_id' => 104,
            ]],
        ], 7);
        $reversal = (new \App\Services\Finance\PlotEntryLifecycleService())->reverse(
            1, 'plot_purchase', $created['source_id'], 'PLOT-VOID-REQUEST-0001',
            '2026-11-05', 'Purchase cancelled by review', 7
        );

        $this->assertDatabaseCount('finance_plot_purchases', 1);
        $this->assertDatabaseCount('finance_plot_purchase_items', 1);
        $this->assertSame(100.0, (float) DB::table('finance_posting_lines')->where('posting_batch_id', $reversal)->sum('debit'));
        $this->assertSame(100.0, (float) DB::table('finance_posting_lines')->where('posting_batch_id', $reversal)->sum('credit'));
        $this->assertSame($created['posting_batch_id'], (int) DB::table('finance_posting_batches')->where('id', $reversal)->value('reversal_of_batch_id'));
        $plotId = (int) DB::table('finance_plot_purchase_items')->where('purchase_id', $created['source_id'])->value('legacy_plot_id');
        $this->assertSame(0, (int) DB::table('plots')->where('id', $plotId)->value('is_active'));
        $this->assertDatabaseHas('plots', ['id' => $plotId, 'project_id' => 1]);
    }

    public function test_purchase_reversal_is_rejected_if_legacy_plot_has_booking_history(): void
    {
        $writer = new PlotEntryWriter();
        $created = $writer->purchase(1, 'PLOT-PURCHASE-REQUEST-BOOKED', [
            'date' => '2026-11-05', 'purchase_number' => 'PP-1', 'seller_account_id' => 102,
            'narration' => 'Booked purchase', 'items' => [[
                'plot_code' => 'LAND-BOOKED', 'description' => 'Booked parcel', 'area' => '1',
                'unit' => 'Kanal', 'type' => 1, 'road_id' => 1, 'facing_id' => 1,
                'unit_rate' => '100', 'inventory_account_id' => 104,
            ]],
        ], 7);
        $plotId = (int) DB::table('finance_plot_purchase_items')->where('purchase_id', $created['source_id'])->value('legacy_plot_id');
        DB::table('bookings')->insert(['id' => 51, 'project_id' => 1, 'customer_id' => 5, 'plot_id' => $plotId, 'status' => 'active', 'cancel_status' => '0']);
        try {
            (new \App\Services\Finance\PlotEntryLifecycleService())->reverse(
                1, 'plot_purchase', $created['source_id'], 'PLOT-VOID-REQUEST-BOOKED',
                '2026-11-05', 'Must remain bookable', 7
            );
            $this->fail('A purchase backing a booked plot must not be reversed.');
        } catch (\DomainException $error) {
            $this->assertSame('V2_PLOT_PURCHASE_INVENTORY_IN_USE', $error->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_batches', 1);
        $this->assertSame(1, (int) DB::table('plots')->where('id', $plotId)->value('is_active'));
    }
    public function test_phase07_readiness_audit_is_project_scoped_and_read_only(): void
    {
        $before = DB::table('finance_posting_batches')->count();
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('finance:audit-v2-plot-receipt', ['project'=>1]));
        $this->assertSame(1, \Illuminate\Support\Facades\Artisan::call('finance:audit-v2-plot-receipt', ['project'=>2]));
        $this->assertSame($before, DB::table('finance_posting_batches')->count());
        $this->assertDatabaseCount('finance_plot_receipts', 0);
    }

    public function test_phase07_local_migration_recreates_only_missing_receipt_storage_and_posts_two_plots(): void
    {
        Schema::drop('finance_plot_receipt_postings');
        Schema::drop('finance_plot_receipt_allocations');
        Schema::drop('finance_plot_receipts');
        $pilot = require base_path('database/local_finance_migrations/2026_11_14_000100_create_finance_plot_receipt_storage.php');
        $pilot->up();
        $pilot->up(); // Running again must not overwrite receipt evidence.
        $this->assertTrue(Schema::hasTable('finance_plot_receipts'));
        $this->assertTrue(Schema::hasTable('finance_plot_receipt_allocations'));
        $this->assertTrue(Schema::hasTable('finance_plot_receipt_postings'));
        $writer = new PlotEntryWriter();
        $payload = ['date' => '2026-11-05', 'receipt_number' => 'PPR-1',
            'amount' => '75.00', 'cash_account_id' => 101,
            'narration' => 'Isolated two plot receipt', 'allocations' => [
                ['booking_id'=>31,'plot_id'=>11,'receivable_account_id'=>102,'amount'=>'40.00'],
                ['booking_id'=>32,'plot_id'=>12,'receivable_account_id'=>103,'amount'=>'35.00'],
            ]];
        $posted = $writer->receive(1, 5, 'LOCAL-PLOT-RECEIPT-REQUEST-0001', $payload, 7);
        $this->assertFalse($posted['replayed']);
        $this->assertSame(75.0, (float) DB::table('finance_posting_lines')
            ->where('posting_batch_id', $posted['posting_batch_id'])->sum('debit'));
        $this->assertSame(75.0, (float) DB::table('finance_posting_lines')
            ->where('posting_batch_id', $posted['posting_batch_id'])->sum('credit'));
        $this->assertDatabaseCount('finance_plot_receipt_allocations', 2);
    }

    public function test_phase07_mysql_claim_fk_repair_refuses_sqlite_without_changes(): void
    {
        $before = DB::table('finance_posting_batches')->count();
        $repair = require base_path('database/local_finance_migrations/2026_11_14_000101_repair_finance_plot_receipt_claim_fk.php');
        try {
            $repair->up();
            $this->fail('MySQL claim recovery must not execute on the SQLite fixture.');
        } catch (\RuntimeException $error) {
            $this->assertSame('V2_PLOT_RECEIPT_FK_REPAIR_MYSQL_ONLY', $error->getMessage());
        }
        $this->assertSame($before, DB::table('finance_posting_batches')->count());
        $this->assertTrue(Schema::hasTable('finance_plot_receipt_postings'));
    }

    public function test_phase07_mysql_claim_fk_repair_checks_driver_even_with_production_environment(): void
    {
        $repair = require base_path('database/local_finance_migrations/2026_11_14_000101_repair_finance_plot_receipt_claim_fk.php');
        config()->set('app.env', 'production');
        try {
            $repair->up();
            $this->fail('SQLite fixture must not run a MySQL-only FK repair.');
        } catch (\RuntimeException $error) {
            $this->assertSame('V2_PLOT_RECEIPT_FK_REPAIR_MYSQL_ONLY', $error->getMessage());
        } finally {
            config()->set('app.env', 'testing');
        }
    }

    public function test_phase07_original_fk_has_safe_mysql_identifier_and_audit_checks_integrity(): void
    {
        $migration = file_get_contents(base_path('database/local_finance_migrations/2026_11_14_000100_create_finance_plot_receipt_storage.php'));
        $repair = file_get_contents(base_path('database/local_finance_migrations/2026_11_14_000101_repair_finance_plot_receipt_claim_fk.php'));
        $audit = file_get_contents(app_path('Console/Commands/AuditPlotReceiptReadiness.php'));
        $this->assertLessThanOrEqual(64, strlen('v2_plot_rcpt_post_claim_fk'));
        $this->assertStringContainsString("'v2_plot_rcpt_post_claim_fk'", $migration);
        $this->assertStringContainsString("'v2_plot_rcpt_post_claim_fk'", $repair);
        $this->assertStringContainsString('V2_PLOT_RECEIPT_INCOMPLETE_FK', $audit);
        $this->assertStringContainsString('information_schema.KEY_COLUMN_USAGE', $audit);
    }

    public function test_phase08_read_only_purchase_reconciliation_matches_inventory_seller_and_project(): void
    {
        $created = (new PlotEntryWriter())->purchase(1, 'PLOT-RECONCILE-REQUEST-0001', [
            'date'=>'2026-11-14', 'purchase_number'=>'PP-1',
            'seller_account_id'=>102, 'narration'=>'Audit pilot',
            'items'=>[[
                'plot_code'=>'PP-AUDIT-PLOT-01', 'description'=>'New plot',
                'area'=>'1.0000', 'unit'=>'Marla', 'type'=>1,
                'road_id'=>1, 'facing_id'=>1, 'is_corner'=>false,
                'unit_rate'=>'100.00', 'inventory_account_id'=>104,
            ]],
        ], 7);
        $audit = new \App\Services\Finance\PlotPurchaseReconciliation();
        $rows = $audit->audit(1);
        $this->assertCount(1,$rows);
        $this->assertTrue($rows[0]['ok'], implode(', ', $rows[0]['issues']));
        $this->assertSame('100.00', $rows[0]['debit']);
        $this->assertSame('100.00', $rows[0]['credit']);
        $this->assertSame('100.00', $rows[0]['amount']);
        $this->assertSame(1, $rows[0]['plots']);
        $this->assertSame([], $audit->audit(2));
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('finance:audit-v2-plot-purchases',['project'=>1]));
        $this->assertSame(1, \Illuminate\Support\Facades\Artisan::call('finance:audit-v2-plot-purchases',['project'=>2]));
        $this->assertDatabaseCount('finance_plot_purchases',1);
        $this->assertDatabaseCount('finance_posting_lines',2);
    }

    public function test_phase08_purchase_reconciliation_detects_corrupted_seller_credit_without_repairing(): void
    {
        $created = (new PlotEntryWriter())->purchase(1, 'PLOT-RECONCILE-REQUEST-0002', [
            'date'=>'2026-11-14', 'purchase_number'=>'PP-1',
            'seller_account_id'=>102, 'narration'=>'Audit pilot',
            'items'=>[[
                'plot_code'=>'PP-AUDIT-PLOT-02', 'description'=>'New plot',
                'area'=>'1.0000', 'unit'=>'Marla', 'type'=>1,
                'road_id'=>1, 'facing_id'=>1, 'is_corner'=>false,
                'unit_rate'=>'50.00', 'inventory_account_id'=>104,
            ]],
        ], 7);
        DB::table('finance_posting_lines')
            ->where('posting_batch_id',$created['posting_batch_id'])
            ->where('project_head_subhead_id',102)->update(['credit'=>'40.00']);
        $rows = (new \App\Services\Finance\PlotPurchaseReconciliation())->audit(1);
        $this->assertFalse($rows[0]['ok']);
        $this->assertContains('PURCHASE_OR_SELLER_AMOUNT_MISMATCH',$rows[0]['issues']);
        $this->assertContains('POSTING_UNBALANCED_OR_LINE_COUNT',$rows[0]['issues']);
        $this->assertSame(40.0,(float) DB::table('finance_posting_lines')
            ->where('posting_batch_id',$created['posting_batch_id'])
            ->where('project_head_subhead_id',102)->value('credit'));
    }

    public function test_phase08_local_migration_installs_purchase_only_and_writer_posts_balanced_inventory_cost(): void
    {
        Schema::drop('finance_plot_purchase_postings');
        Schema::drop('finance_plot_purchase_items');
        Schema::drop('finance_plot_purchases');

        $receiptCount = DB::table('finance_plot_receipts')->count();
        $pilot = require base_path('database/local_finance_migrations/2026_11_14_000200_create_finance_plot_purchase_storage.php');
        $pilot->up();
        $pilot->up(); // A repeat must not wipe already installed storage.
        $this->assertSame($receiptCount, DB::table('finance_plot_receipts')->count());
        $this->assertTrue(Schema::hasTable('finance_plot_purchases'));
        $this->assertTrue(Schema::hasTable('finance_plot_purchase_items'));
        $this->assertTrue(Schema::hasTable('finance_plot_purchase_postings'));

        $created = (new PlotEntryWriter())->purchase(1, 'PLOT-PURCHASE-PHASE08-TEST-0001', [
            'date' => '2026-11-14', 'purchase_number' => 'PP-1',
            'seller_account_id' => 102, 'narration' => 'Phase 08 isolated pilot purchase',
            'items' => [[
                'plot_code' => 'PHASE08-LOT-001', 'description' => 'New plot',
                'area' => '1.5000', 'unit' => 'Kanal', 'type' => 1,
                'road_id' => 1, 'facing_id' => 1, 'is_corner' => false,
                'unit_rate' => '100.00', 'inventory_account_id' => 104,
            ]],
        ], 7);
        $this->assertFalse($created['replayed']);
        $this->assertDatabaseCount('finance_plot_purchases', 1);
        $this->assertDatabaseCount('finance_plot_purchase_items', 1);
        $this->assertSame(150.0, (float) DB::table('finance_posting_lines')
            ->where('posting_batch_id', $created['posting_batch_id'])->sum('debit'));
        $this->assertSame(150.0, (float) DB::table('finance_posting_lines')
            ->where('posting_batch_id', $created['posting_batch_id'])->sum('credit'));
    }

    public function test_phase08_local_migration_rejects_partial_storage_without_dropping_purchase_data(): void
    {
        Schema::drop('finance_plot_purchase_postings');
        $pilot = require base_path('database/local_finance_migrations/2026_11_14_000200_create_finance_plot_purchase_storage.php');
        try {
            $pilot->up();
            $this->fail('Partially installed purchase storage must stop rather than rebuilding.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('V2_PLOT_PURCHASE_PARTIAL_STORAGE', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('finance_plot_purchases'));
        $this->assertTrue(Schema::hasTable('finance_plot_purchase_items'));
    }

    public function test_phase08_existing_storage_is_unchanged_with_production_environment(): void
    {
        $pilot = require base_path('database/local_finance_migrations/2026_11_14_000200_create_finance_plot_purchase_storage.php');
        $before=Schema::hasTable('finance_plot_purchases');
        config()->set('app.env', 'production');
        try {
            $pilot->up();
            $this->assertSame($before,Schema::hasTable('finance_plot_purchases'));
            $this->assertTrue(Schema::hasTable('finance_plot_purchase_postings'));
        } finally {
            config()->set('app.env', 'testing');
        }
    }

    public function test_phase08_readiness_command_is_read_only_and_project_scoped(): void
    {
        $before = DB::table('finance_plot_purchases')->count();
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('finance:audit-v2-plot-purchase', ['project' => 1]));
        $this->assertSame(1, \Illuminate\Support\Facades\Artisan::call('finance:audit-v2-plot-purchase', ['project' => 2]));
        $this->assertSame($before, DB::table('finance_plot_purchases')->count());
    }

    public function test_phase07_complete_storage_is_preserved_on_configured_connection(): void
    {
        $pilot = require base_path('database/local_finance_migrations/2026_11_14_000100_create_finance_plot_receipt_storage.php');
        $before=DB::table('finance_plot_receipts')->count();
        config()->set('app.env', 'production');
        try {
            $pilot->up();
            $this->assertSame($before,DB::table('finance_plot_receipts')->count());
            $this->assertTrue(Schema::hasTable('finance_plot_receipt_postings'));
        } finally {
            config()->set('app.env', 'testing');
        }
    }

    public function test_phase07_local_migration_rejects_partially_existing_receipt_storage(): void
    {
        Schema::drop('finance_plot_receipt_postings');
        $pilot = require base_path('database/local_finance_migrations/2026_11_14_000100_create_finance_plot_receipt_storage.php');
        try {
            $pilot->up();
            $this->fail('Partially existing historical finance storage must never be rebuilt.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('V2_PLOT_RECEIPT_PARTIAL_STORAGE', $error->getMessage());
        }
        $this->assertTrue(Schema::hasTable('finance_plot_receipts'));
        $this->assertTrue(Schema::hasTable('finance_plot_receipt_allocations'));
        $this->assertFalse(Schema::hasTable('finance_plot_receipt_postings'));
    }


    public function test_archived_plot_receipt_is_not_available_in_details_or_idempotent_replay(): void
    {
        // The existing test fixture represents the baseline before the new
        // source lifecycle migration. Add only the root lifecycle columns.
        Schema::table('finance_plot_receipts', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
        });
        $writer = new PlotEntryWriter();
        $payload = [
            'date' => '2026-11-14',
            'receipt_number' => 'PPR-1',
            'amount' => '75.50',
            'cash_account_id' => 101,
            'narration' => 'Archived source must stay hidden',
            'allocations' => [[
                'booking_id' => 31, 'plot_id' => 11,
                'receivable_account_id' => 102, 'amount' => '75.50',
            ]],
        ];
        $created = $writer->receive(1,5,'PLOT-ARCHIVE-IDEMPOTENCY-0001',$payload,7);
        $this->assertSame(1, (int) DB::table('finance_plot_receipts')
            ->where('id',$created['source_id'])->value('is_active'));

        DB::table('finance_plot_receipts')->where('id',$created['source_id'])
            ->update(['is_active'=>0,'deleted_at'=>now()]);
        $this->assertDatabaseCount('finance_plot_receipts',1);
        $this->assertDatabaseCount('finance_posting_batches',1);

        try {
            $writer->receipt(1,$created['source_id']);
            $this->fail('Archived source must not be readable as an active receipt.');
        } catch (\DomainException $error) {
            $this->assertSame('V2_PLOT_RECEIPT_NOT_FOUND',$error->getMessage());
        }

        try {
            $writer->receive(1,5,'PLOT-ARCHIVE-IDEMPOTENCY-0001',$payload,7);
            $this->fail('Idempotency replay must not resurrect archived sources.');
        } catch (\DomainException $error) {
            $this->assertSame('PLOT_SOURCE_ARCHIVED',$error->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_batches',1);
        $this->assertDatabaseCount('finance_plot_receipt_allocations',1);
    }

    public function test_archived_plot_purchase_is_inaccessible_without_erasing_inventory_or_posting(): void
    {
        Schema::table('finance_plot_purchases', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
        });
        $writer = new PlotEntryWriter();
        $payload = [
            'date' => '2026-11-05',
            'purchase_number' => 'PP-1',
            'seller_account_id' => 102,
            'narration' => 'Archive guard test',
            'items' => [[
                'plot_code'=>'LAND-ARCHIVE-1',
                'description'=>'Temporary test plot',
                'area'=>'1.0000','unit'=>'Kanal','type'=>1,
                'road_id'=>1,'facing_id'=>1,'is_corner'=>false,
                'unit_rate'=>'100.00','inventory_account_id'=>104,
            ]],
        ];
        $created=$writer->purchase(1,'PLOT-ARCHIVE-PURCHASE-0001',$payload,7);
        DB::table('finance_plot_purchases')->where('id',$created['source_id'])
            ->update(['is_active'=>0,'deleted_at'=>now()]);
        try {
            $writer->purchaseDetails(1,$created['source_id']);
            $this->fail('Archived purchase must not be available as a live voucher.');
        } catch (\DomainException $error) {
            $this->assertSame('V2_PLOT_PURCHASE_NOT_FOUND',$error->getMessage());
        }
        try {
            $writer->purchase(1,'PLOT-ARCHIVE-PURCHASE-0001',$payload,7);
            $this->fail('Archived purchase idempotency key must not revive it.');
        } catch (\DomainException $error) {
            $this->assertSame('PLOT_SOURCE_ARCHIVED',$error->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_batches',1);
        $this->assertDatabaseCount('finance_plot_purchase_items',1);
        $this->assertDatabaseCount('finance_plot_purchases',1);
    }

    public function test_archived_plot_receipt_cannot_be_reversed_or_create_any_posting(): void
    {
        Schema::table('finance_plot_receipts', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
        });
        $created=(new PlotEntryWriter())->receive(1, 5, 'PLOT-RECEIPT-ARCHIVED-REV-001', [
            'date'=>'2026-11-05','receipt_number'=>'PPR-1',
            'amount'=>'75.00','cash_account_id'=>101,'narration'=>'Archive reversal guard',
            'allocations'=>[['booking_id'=>31,'plot_id'=>11,'receivable_account_id'=>102,'amount'=>'75.00']],
        ],7);
        DB::table('finance_plot_receipts')->where('id',$created['source_id'])
            ->update(['is_active'=>0,'deleted_at'=>now()]);
        try {
            (new \App\Services\Finance\PlotEntryLifecycleService())->reverse(
                1,'plot_receipt',$created['source_id'],'PLOT-RECEIPT-ARCHIVED-VOID1',
                '2026-11-05','Archived receipt cannot reverse',7
            );
            $this->fail('Archived receipt must not produce a new posting batch.');
        } catch (\DomainException $error) {
            $this->assertSame('PLOT_SOURCE_NOT_ACTIVE',$error->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_batches',1);
        $this->assertDatabaseCount('finance_posting_lines',2);
    }

    public function test_archived_plot_purchase_cannot_reverse_or_deactivate_its_inventory(): void
    {
        Schema::table('finance_plot_purchases', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
        });
        $created=(new PlotEntryWriter())->purchase(1,'PLOT-PURCHASE-ARCHIVED-REV-001',[
            'date'=>'2026-11-05','purchase_number'=>'PP-1',
            'seller_account_id'=>102,'narration'=>'Archive purchase reversal guard',
            'items'=>[[
                'plot_code'=>'LAND-ARCHIVED-REV-1','description'=>'Temporary plot','area'=>'1',
                'unit'=>'Kanal','type'=>1,'road_id'=>1,'facing_id'=>1,
                'unit_rate'=>'100','inventory_account_id'=>104,
            ]],
        ],7);
        DB::table('finance_plot_purchases')->where('id',$created['source_id'])
            ->update(['is_active'=>0,'deleted_at'=>now()]);
        $plotId=(int)DB::table('finance_plot_purchase_items')
            ->where('purchase_id',$created['source_id'])->value('legacy_plot_id');
        try {
            (new \App\Services\Finance\PlotEntryLifecycleService())->reverse(
                1,'plot_purchase',$created['source_id'],'PLOT-PURCHASE-ARCHIVED-VOID1',
                '2026-11-05','Archived purchase cannot reverse',7
            );
            $this->fail('Archived purchase must not produce a new posting batch.');
        } catch (\DomainException $error) {
            $this->assertSame('PLOT_SOURCE_NOT_ACTIVE',$error->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_batches',1);
        $this->assertSame(1,(int)DB::table('plots')->where('id',$plotId)->value('is_active'));
    }

    public function test_plot_receipt_narration_edit_is_transactional_audited_and_preserves_accounting(): void
    {
        Schema::create('daily_voucher_edit_snapshots',function(Blueprint $t): void {
            $t->id();$t->unsignedBigInteger('project_id');$t->unsignedBigInteger('source_id');
            $t->unsignedBigInteger('approval_id')->nullable();$t->string('status',16);
            $t->longText('before_snapshot');$t->longText('proposed_snapshot');
            $t->text('reason');$t->unsignedBigInteger('submitted_by');
            $t->unsignedBigInteger('decided_by')->nullable();$t->timestamps();
        });
        $payload=[
            'date'=>'2026-11-05','receipt_number'=>'PPR-1','amount'=>'75.00',
            'cash_account_id'=>101,'narration'=>'Before receipt edit',
            'allocations'=>[['booking_id'=>31,'plot_id'=>11,'receivable_account_id'=>102,'amount'=>'75.00']],
        ];
        $created=(new PlotEntryWriter())->receive(1,5,'PLOT-RECEIPT-NARRATION-0001',$payload,7);
        $before=DB::table('finance_posting_lines')->where('posting_batch_id',$created['posting_batch_id'])->orderBy('id')->get()->map(fn($row)=>(array)$row)->all();
        $result=(new \App\Services\Finance\PlotVoucherNarrationEditor())
            ->update(1,'plot_receipt',$created['source_id'],'Updated receipt note',7);
        $this->assertTrue($result['changed']);
        $this->assertSame('Updated receipt note',DB::table('finance_plot_receipts')
            ->where('id',$created['source_id'])->value('narration'));
        $this->assertSame($before,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$created['posting_batch_id'])->orderBy('id')->get()->map(fn($row)=>(array)$row)->all());
        $this->assertDatabaseHas('daily_voucher_edit_snapshots',[
            'project_id'=>1,'source_id'=>$created['source_id'],
            'status'=>'plot_note','submitted_by'=>7,'decided_by'=>7,
        ]);
        $snapshot=DB::table('daily_voucher_edit_snapshots')->first();
        $this->assertSame('plot_receipt',json_decode($snapshot->before_snapshot,true)['source_kind']);
        $this->assertSame('Before receipt edit',json_decode($snapshot->before_snapshot,true)['narration']);
        $this->assertSame('Updated receipt note',json_decode($snapshot->proposed_snapshot,true)['narration']);
        $this->assertDatabaseCount('finance_posting_batches',1);
        $this->assertSame(1,DB::table('finance_plot_receipt_allocations')->count());
        try{
            (new \App\Services\Finance\PlotVoucherNarrationEditor())
                ->update(2,'plot_receipt',$created['source_id'],'Cross project',7);
            $this->fail('Project ownership must reject the edit');
        }catch(\DomainException $error){
            $this->assertStringContainsString('not found',$error->getMessage());
        }
        $this->assertDatabaseCount('daily_voucher_edit_snapshots',1);
    }

    public function test_plot_purchase_narration_edit_rejects_reversed_source_without_changes(): void
    {
        Schema::create('daily_voucher_edit_snapshots',function(Blueprint $t): void {
            $t->id();$t->unsignedBigInteger('project_id');$t->unsignedBigInteger('source_id');
            $t->unsignedBigInteger('approval_id')->nullable();$t->string('status',16);
            $t->longText('before_snapshot');$t->longText('proposed_snapshot');
            $t->text('reason');$t->unsignedBigInteger('submitted_by');
            $t->unsignedBigInteger('decided_by')->nullable();$t->timestamps();
        });
        $created=(new PlotEntryWriter())->purchase(1,'PLOT-PURCHASE-NARRATION-0001',[
            'date'=>'2026-11-05','purchase_number'=>'PP-1','seller_account_id'=>102,
            'narration'=>'Original purchase note','items'=>[[
                'plot_code'=>'LAND-NARRATION-1','description'=>'Plot','area'=>'1',
                'unit'=>'Kanal','type'=>1,'road_id'=>1,'facing_id'=>1,
                'unit_rate'=>'100','inventory_account_id'=>104,
            ]],
        ],7);
        $editor=new \App\Services\Finance\PlotVoucherNarrationEditor();
        $result=$editor->update(1,'plot_purchase',$created['source_id'],'New purchase note',7);
        $this->assertTrue($result['changed']);
        $this->assertDatabaseCount('daily_voucher_edit_snapshots',1);
        (new \App\Services\Finance\PlotEntryLifecycleService())->reverse(
            1,'plot_purchase',$created['source_id'],'PLOT-PURCHASE-NARRATION-VOID1',
            '2026-11-05','Close purchase',7
        );
        try{
            $editor->update(1,'plot_purchase',$created['source_id'],'Not allowed after reverse',7);
            $this->fail('Reversed purchase must not be editable');
        }catch(\DomainException $error){
            $this->assertStringContainsString('reversed',$error->getMessage());
        }
        $this->assertSame('New purchase note',DB::table('finance_plot_purchases')
            ->where('id',$created['source_id'])->value('narration'));
        $this->assertDatabaseCount('daily_voucher_edit_snapshots',1);
        $this->assertDatabaseCount('finance_posting_batches',2);
    }

    public function test_legacy_plot_edit_audit_consolidates_into_shared_snapshots_before_removing_extra_table(): void
    {
        Schema::create('daily_voucher_edit_snapshots',function(Blueprint $t): void {
            $t->id();$t->unsignedBigInteger('project_id');$t->unsignedBigInteger('source_id');
            $t->unsignedBigInteger('approval_id')->nullable();$t->string('status',16);
            $t->longText('before_snapshot');$t->longText('proposed_snapshot');
            $t->text('reason');$t->unsignedBigInteger('submitted_by');
            $t->unsignedBigInteger('decided_by')->nullable();$t->timestamps();
        });
        (require base_path('database/migrations/2026_11_18_170000_create_plot_voucher_edit_audits_table.php'))->up();
        DB::table('finance_plot_voucher_edit_audits')->insert([
            'project_id'=>1,'source_kind'=>'plot_receipt','source_id'=>15,
            'previous_narration'=>'Old original','updated_narration'=>'Updated existing',
            'edited_by'=>7,'created_at'=>now(),'updated_at'=>now(),
        ]);
        (require base_path('database/migrations/2026_11_18_180000_consolidate_plot_narration_audit.php'))->up();
        $this->assertFalse(Schema::hasTable('finance_plot_voucher_edit_audits'));
        $this->assertDatabaseCount('daily_voucher_edit_snapshots',1);
        $saved=DB::table('daily_voucher_edit_snapshots')->first();
        $this->assertSame('plot_note',$saved->status);
        $this->assertSame('Old original',json_decode($saved->before_snapshot,true)['narration']);
        $this->assertSame('Updated existing',json_decode($saved->proposed_snapshot,true)['narration']);
        (require base_path('database/migrations/2026_11_18_180000_consolidate_plot_narration_audit.php'))->up();
        $this->assertDatabaseCount('daily_voucher_edit_snapshots',1);
    }
    private function installFullPlotEditHistory(): void
    {
        Schema::create('daily_voucher_edit_snapshots',function(Blueprint $t): void {
            $t->id();$t->unsignedBigInteger('project_id');$t->unsignedBigInteger('source_id');
            $t->unsignedBigInteger('approval_id')->nullable();$t->string('status',16);
            $t->longText('before_snapshot');$t->longText('proposed_snapshot');
            $t->text('reason');$t->unsignedBigInteger('submitted_by');
            $t->unsignedBigInteger('decided_by')->nullable();$t->timestamps();
        });
    }

    public function test_full_plot_receipt_edit_updates_source_allocations_and_existing_balanced_posting(): void
    {
        $this->installFullPlotEditHistory();
        $original=(new PlotEntryWriter())->receive(1,5,'PLOT-RECEIPT-FULL-EDIT-001',[
            'date'=>'2026-11-05','receipt_number'=>'PPR-1','amount'=>'70.00',
            'cash_account_id'=>101,'narration'=>'Before full edit',
            'allocations'=>[['booking_id'=>31,'plot_id'=>11,'receivable_account_id'=>102,'amount'=>'70.00']],
        ],7);
        $lineIds=DB::table('finance_posting_lines')->where('posting_batch_id',$original['posting_batch_id'])
            ->orderBy('id')->pluck('id')->all();
        $changed=(new \App\Services\Finance\PlotVoucherEditor())->receipt(1,$original['source_id'],[
            'customer_id'=>5,'receipt_number'=>'PR-FULL-UPDATED','date'=>'2026-11-06',
            'amount'=>'81.50','cash_account_id'=>101,'narration'=>'Full fields updated',
            'allocations'=>[['booking_id'=>32,'plot_id'=>12,'receivable_account_id'=>103,'amount'=>'81.50']],
        ],7);
        $this->assertSame($original['source_id'],$changed['source_id']);
        $this->assertSame($original['posting_batch_id'],$changed['posting_batch_id']);
        $this->assertDatabaseHas('finance_plot_receipts',['id'=>$original['source_id'],
            'receipt_number'=>'PR-FULL-UPDATED','amount'=>81.5,'narration'=>'Full fields updated']);
        $this->assertDatabaseHas('finance_plot_receipt_allocations',['receipt_id'=>$original['source_id'],
            'booking_id'=>32,'plot_id'=>12,'amount'=>81.5]);
        $lines=DB::table('finance_posting_lines')->where('posting_batch_id',$original['posting_batch_id'])
            ->orderBy('id')->get();
        $this->assertSame($lineIds, $lines->take(2)->pluck('id')->all());
        $this->assertCount(3,$lines); // removed booking leg is retained as archived evidence
        $this->assertSame(0,(int)$lines[1]->is_active);
        $this->assertSame(1,(int)$lines[2]->is_active);
        $this->assertSame(81.5,(float)$lines->sum('debit'));
        $this->assertSame(81.5,(float)$lines->sum('credit'));
        $this->assertSame(103,(int)$lines[2]->project_head_subhead_id);
        $this->assertDatabaseCount('finance_posting_batches',1);
        $this->assertDatabaseHas('daily_voucher_edit_snapshots',['project_id'=>1,'source_id'=>$original['source_id'],
            'status'=>'plot_edit','submitted_by'=>7]);
        $snapshot=DB::table('daily_voucher_edit_snapshots')->first();
        $this->assertSame('PPR-1',json_decode($snapshot->before_snapshot,true)['voucher']['receipt_number']);
        $this->assertSame('PR-FULL-UPDATED',json_decode($snapshot->proposed_snapshot,true)['voucher']['receipt_number']);
        try {
            (new PlotEntryWriter())->receive(1,5,'PLOT-RECEIPT-FULL-EDIT-001',[
                'date'=>'2026-11-05','receipt_number'=>'PPR-1','amount'=>'70.00',
                'cash_account_id'=>101,'narration'=>'Before full edit',
                'allocations'=>[['booking_id'=>31,'plot_id'=>11,'receivable_account_id'=>102,'amount'=>'70.00']],
            ],7);
            $this->fail('Original request key must not replay old payload after source edit.');
        } catch (\DomainException $error) {
            $this->assertSame('PLOT_SOURCE_HAS_BEEN_EDITED',$error->getMessage());
        }
    }

    public function test_full_purchase_edit_updates_existing_inventory_and_cost_without_new_batch(): void
    {
        $this->installFullPlotEditHistory();
        $original=(new PlotEntryWriter())->purchase(1,'PLOT-PURCHASE-FULL-EDIT-001',[
            'date'=>'2026-11-05','purchase_number'=>'PP-1','seller_account_id'=>102,
            'narration'=>'Before edit','items'=>[[
                'plot_code'=>'LAND-EDIT-OLD','description'=>'Before','area'=>'1',
                'unit'=>'Kanal','type'=>1,'road_id'=>1,'facing_id'=>1,
                'unit_rate'=>'100','inventory_account_id'=>104,
            ]],
        ],7);
        $plotId=(int)DB::table('finance_plot_purchase_items')->where('purchase_id',$original['source_id'])
            ->value('legacy_plot_id');
        $updated=(new \App\Services\Finance\PlotVoucherEditor())->purchase(1,$original['source_id'],[
            'date'=>'2026-11-06','purchase_number'=>'PP-FULL-UPDATED','seller_account_id'=>102,
            'narration'=>'Updated purchase','items'=>[[
                'legacy_plot_id'=>$plotId,'plot_code'=>'LAND-EDIT-NEW','description'=>'After','area'=>'2',
                'unit'=>'Kanal','type'=>1,'road_id'=>1,'facing_id'=>1,
                'unit_rate'=>'150','inventory_account_id'=>104,
            ]],
        ],7);
        $this->assertSame($original['source_id'],$updated['source_id']);
        $this->assertSame($original['posting_batch_id'],$updated['posting_batch_id']);
        $this->assertDatabaseHas('plots',['id'=>$plotId,'project_id'=>1,'name'=>'LAND-EDIT-NEW']);
        $this->assertDatabaseHas('finance_plot_purchase_items',['purchase_id'=>$original['source_id'],
            'legacy_plot_id'=>$plotId,'plot_code'=>'LAND-EDIT-NEW','amount'=>300]);
        $this->assertDatabaseHas('finance_plot_purchases',['id'=>$original['source_id'],
            'purchase_number'=>'PP-FULL-UPDATED','amount'=>300]);
        $lines=DB::table('finance_posting_lines')->where('posting_batch_id',$original['posting_batch_id'])->get();
        $this->assertSame(300.0,(float)$lines->sum('debit'));
        $this->assertSame(300.0,(float)$lines->sum('credit'));
        $this->assertDatabaseCount('finance_posting_batches',1);
        $this->assertDatabaseCount('daily_voucher_edit_snapshots',1);
    }

    public function test_full_receipt_edit_refuses_corrupt_allocation_posting_relationship(): void
    {
        $this->installFullPlotEditHistory();
        $created=(new PlotEntryWriter())->receive(1,5,'PLOT-RECEIPT-REL-GUARD-001',[
            'date'=>'2026-11-05','receipt_number'=>'PPR-1','amount'=>'70.00',
            'cash_account_id'=>101,'narration'=>'Original',
            'allocations'=>[['booking_id'=>31,'plot_id'=>11,'receivable_account_id'=>102,'amount'=>'70.00']],
        ],7);
        DB::table('finance_posting_lines')->where('posting_batch_id',$created['posting_batch_id'])
            ->where('credit','>',0)->update(['project_head_subhead_id'=>103]);
        try {
            (new \App\Services\Finance\PlotVoucherEditor())->receipt(1,$created['source_id'],[
                'customer_id'=>5,'receipt_number'=>'PR-REL-MUTATED','date'=>'2026-11-06',
                'amount'=>'70.00','cash_account_id'=>101,'narration'=>'Should reject',
                'allocations'=>[['booking_id'=>31,'plot_id'=>11,
                    'receivable_account_id'=>102,'amount'=>'70.00']],
            ],7);
            $this->fail('Corrupt linked credit account must refuse edits.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('relationship mismatch',$e->getMessage());
        }
        $this->assertSame('PPR-1',DB::table('finance_plot_receipts')
            ->where('id',$created['source_id'])->value('receipt_number'));
        $this->assertSame(0,DB::table('daily_voucher_edit_snapshots')
            ->where('status','plot_edit')->count());
        $this->assertDatabaseCount('finance_posting_batches',1);
    }

    public function test_full_purchase_edit_refuses_corrupt_inventory_posting_relationship(): void
    {
        $this->installFullPlotEditHistory();
        $created=(new PlotEntryWriter())->purchase(1,'PLOT-PURCHASE-REL-GUARD-001',[
            'date'=>'2026-11-05','purchase_number'=>'PP-1','seller_account_id'=>102,
            'narration'=>'Original','items'=>[[
                'plot_code'=>'LAND-REL-GUARD','description'=>'Original','area'=>'1',
                'unit'=>'Kanal','type'=>1,'road_id'=>1,'facing_id'=>1,
                'unit_rate'=>'100','inventory_account_id'=>104,
            ]],
        ],7);
        $plotId=(int)DB::table('finance_plot_purchase_items')
            ->where('purchase_id',$created['source_id'])->value('legacy_plot_id');
        DB::table('finance_posting_lines')->where('posting_batch_id',$created['posting_batch_id'])
            ->where('debit','>',0)->update(['debit'=>'101.00']);
        try {
            (new \App\Services\Finance\PlotVoucherEditor())->purchase(1,$created['source_id'],[
                'date'=>'2026-11-06','purchase_number'=>'PP-REL-MUTATED','seller_account_id'=>102,
                'narration'=>'Should reject','items'=>[[
                    'legacy_plot_id'=>$plotId,'plot_code'=>'LAND-REL-GUARD',
                    'description'=>'Invalid','area'=>'1','unit'=>'Kanal','type'=>1,
                    'road_id'=>1,'facing_id'=>1,'unit_rate'=>'150','inventory_account_id'=>104,
                ]],
            ],7);
            $this->fail('Corrupt inventory posting leg must refuse edits.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('relationship mismatch',$e->getMessage());
        }
        $this->assertSame('PP-1',DB::table('finance_plot_purchases')
            ->where('id',$created['source_id'])->value('purchase_number'));
        $this->assertSame(0,DB::table('daily_voucher_edit_snapshots')
            ->where('status','plot_edit')->count());
        $this->assertDatabaseCount('finance_posting_batches',1);
    }

    public function test_receipt_reordering_keeps_booking_posting_line_ids_and_does_not_duplicate_rows(): void
    {
        $this->installFullPlotEditHistory();
        $created=(new PlotEntryWriter())->receive(1,5,'PLOT-RECEIPT-REORDER-EDIT-001',[
            'date'=>'2026-11-05','receipt_number'=>'PPR-1','amount'=>'100.00',
            'cash_account_id'=>101,'narration'=>'Before',
            'allocations'=>[
                ['booking_id'=>31,'plot_id'=>11,'receivable_account_id'=>102,'amount'=>'60.00'],
                ['booking_id'=>32,'plot_id'=>12,'receivable_account_id'=>103,'amount'=>'40.00'],
            ],
        ],7);
        $batch=$created['posting_batch_id'];
        $originalIds=DB::table('finance_posting_lines')->where('posting_batch_id',$batch)
            ->orderBy('id')->pluck('id')->all();
        $editor=new \App\Services\Finance\PlotVoucherEditor();
        $data=[
            'customer_id'=>5,'receipt_number'=>'PPR-1','date'=>'2026-11-05',
            'amount'=>'100.00','cash_account_id'=>101,'narration'=>'After',
            'allocations'=>[
                ['booking_id'=>32,'plot_id'=>12,'receivable_account_id'=>103,'amount'=>'35.00'],
                ['booking_id'=>31,'plot_id'=>11,'receivable_account_id'=>102,'amount'=>'65.00'],
            ],
        ];
        $editor->receipt(1,$created['source_id'],$data,7);
        $editor->receipt(1,$created['source_id'],$data,7);
        $lines=DB::table('finance_posting_lines')->where('posting_batch_id',$batch)->orderBy('id')->get();
        $this->assertSame($originalIds,$lines->pluck('id')->all());
        $this->assertSame(65.0,(float)$lines[1]->credit);
        $this->assertSame(35.0,(float)$lines[2]->credit);
        $this->assertSame(100.0,(float)$lines->sum('debit'));
        $this->assertSame(100.0,(float)$lines->sum('credit'));
        $this->assertSame(2,DB::table('finance_plot_receipt_allocations')
            ->where('receipt_id',$created['source_id'])->count());
        $this->assertDatabaseCount('finance_posting_batches',1);
    }

    public function test_purchase_reordering_preserves_plot_and_posting_ids_across_repeated_edits(): void
    {
        $this->installFullPlotEditHistory();
        $created=(new PlotEntryWriter())->purchase(1,'PLOT-PURCHASE-REORDER-EDIT-001',[
            'date'=>'2026-11-05','purchase_number'=>'PP-1','seller_account_id'=>102,
            'narration'=>'Initial','items'=>[
                ['plot_code'=>'LAND-ORDER-A','description'=>'A','area'=>'1',
                    'unit'=>'Kanal','type'=>1,'road_id'=>1,'facing_id'=>1,
                    'unit_rate'=>'100','inventory_account_id'=>104],
                ['plot_code'=>'LAND-ORDER-B','description'=>'B','area'=>'1',
                    'unit'=>'Kanal','type'=>1,'road_id'=>1,'facing_id'=>1,
                    'unit_rate'=>'200','inventory_account_id'=>104],
            ],
        ],7);
        $source=$created['source_id'];
        $batch=$created['posting_batch_id'];
        $beforeItems=DB::table('finance_plot_purchase_items')->where('purchase_id',$source)
            ->orderBy('id')->get();
        $beforeLines=DB::table('finance_posting_lines')->where('posting_batch_id',$batch)
            ->orderBy('id')->get();
        $form=[
            'date'=>'2026-11-06','purchase_number'=>'PP-1','seller_account_id'=>102,
            'narration'=>'Reordered','items'=>[
                ['legacy_plot_id'=>$beforeItems[1]->legacy_plot_id,
                    'plot_code'=>'LAND-ORDER-B','description'=>'B revised','area'=>'1',
                    'unit'=>'Kanal','type'=>1,'road_id'=>1,'facing_id'=>1,
                    'unit_rate'=>'225','inventory_account_id'=>104],
                ['legacy_plot_id'=>$beforeItems[0]->legacy_plot_id,
                    'plot_code'=>'LAND-ORDER-A','description'=>'A revised','area'=>'1',
                    'unit'=>'Kanal','type'=>1,'road_id'=>1,'facing_id'=>1,
                    'unit_rate'=>'125','inventory_account_id'=>104],
            ],
        ];
        $editor=new \App\Services\Finance\PlotVoucherEditor();
        $editor->purchase(1,$source,$form,7);
        $editor->purchase(1,$source,$form,7);
        $afterLines=DB::table('finance_posting_lines')->where('posting_batch_id',$batch)
            ->orderBy('id')->get();
        $this->assertSame($beforeLines->pluck('id')->all(),$afterLines->pluck('id')->all());
        $this->assertSame(125.0,(float)$afterLines[0]->debit);
        $this->assertSame(225.0,(float)$afterLines[1]->debit);
        $this->assertSame(350.0,(float)$afterLines[2]->credit);
        $this->assertSame($beforeItems->pluck('id')->all(),
            DB::table('finance_plot_purchase_items')->where('purchase_id',$source)
                ->orderBy('id')->pluck('id')->all());
        $this->assertSame(2,DB::table('plots')
            ->whereIn('id',$beforeItems->pluck('legacy_plot_id')->all())->count());
        $this->assertDatabaseCount('finance_posting_batches',1);
    }

    public function test_full_receipt_edit_rejects_duplicate_booking_id_without_financial_writes(): void
    {
        $this->installFullPlotEditHistory();
        $created=(new PlotEntryWriter())->receive(1,5,'PLOT-RECEIPT-DUP-EDIT-001',[
            'date'=>'2026-11-05','receipt_number'=>'PPR-1','amount'=>'70.00',
            'cash_account_id'=>101,'narration'=>'Original',
            'allocations'=>[['booking_id'=>31,'plot_id'=>11,'receivable_account_id'=>102,'amount'=>'70.00']],
        ],7);
        $batchId=$created['posting_batch_id'];
        $sourceId=$created['source_id'];
        $beforeLines=DB::table('finance_posting_lines')->where('posting_batch_id',$batchId)
            ->orderBy('id')->get()->map(fn($row)=>(array)$row)->all();
        try {
            (new \App\Services\Finance\PlotVoucherEditor())->receipt(1,$sourceId,[
                'customer_id'=>5,'receipt_number'=>'PR-DUP-CHANGED','date'=>'2026-11-06',
                'amount'=>'90.00','cash_account_id'=>101,'narration'=>'Duplicate booking',
                'allocations'=>[
                    ['booking_id'=>31,'plot_id'=>11,'receivable_account_id'=>102,'amount'=>'45.00'],
                    ['booking_id'=>31,'plot_id'=>12,'receivable_account_id'=>103,'amount'=>'45.00'],
                ],
            ],7);
            $this->fail('Duplicate booking allocation must fail validation.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Duplicate booking', $e->getMessage());
        }
        $this->assertSame('PPR-1',DB::table('finance_plot_receipts')->where('id',$sourceId)->value('receipt_number'));
        $this->assertSame(1,DB::table('finance_plot_receipt_allocations')->where('receipt_id',$sourceId)->count());
        $this->assertSame($beforeLines,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batchId)->orderBy('id')->get()->map(fn($row)=>(array)$row)->all());
        $this->assertSame(0,DB::table('daily_voucher_edit_snapshots')
            ->where('status','plot_edit')->where('source_id',$sourceId)->count());
    }

    public function test_full_receipt_edit_adds_and_archives_allocation_rows_without_hard_delete(): void
    {
        $this->installFullPlotEditHistory();
        $created=(new PlotEntryWriter())->receive(1,5,'PLOT-RECEIPT-COUNT-EDIT-001',[
            'date'=>'2026-11-05','receipt_number'=>'PPR-1','amount'=>'70.00',
            'cash_account_id'=>101,'narration'=>'Before',
            'allocations'=>[['booking_id'=>31,'plot_id'=>11,'receivable_account_id'=>102,'amount'=>'70.00']],
        ],7);
        $editor=new \App\Services\Finance\PlotVoucherEditor();
        $result=$editor->receipt(1,$created['source_id'],[
            'customer_id'=>5,'receipt_number'=>'PR-COUNT-MOD','date'=>'2026-11-06',
            'amount'=>'90.00','cash_account_id'=>101,'narration'=>'Two allocations',
            'allocations'=>[
                ['booking_id'=>31,'plot_id'=>11,'receivable_account_id'=>102,'amount'=>'50.00'],
                ['booking_id'=>32,'plot_id'=>12,'receivable_account_id'=>103,'amount'=>'40.00'],
            ],
        ],7);
        $this->assertSame($created['source_id'],$result['source_id']);
        $this->assertSame(2,DB::table('finance_plot_receipt_allocations')
            ->where('receipt_id',$created['source_id'])->where('is_active',1)->count());
        $this->assertSame(90.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$created['posting_batch_id'])->sum('debit'));
        $this->assertSame(90.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$created['posting_batch_id'])->sum('credit'));
        $editor->receipt(1,$created['source_id'],[
            'customer_id'=>5,'receipt_number'=>'PR-COUNT-FINAL','date'=>'2026-11-07',
            'amount'=>'55.00','cash_account_id'=>101,'narration'=>'One allocation',
            'allocations'=>[
                ['booking_id'=>32,'plot_id'=>12,'receivable_account_id'=>103,'amount'=>'55.00'],
            ],
        ],7);
        $this->assertSame(1,DB::table('finance_plot_receipt_allocations')
            ->where('receipt_id',$created['source_id'])->where('is_active',1)->count());
        $this->assertSame(1,DB::table('finance_plot_receipt_allocations')
            ->where('receipt_id',$created['source_id'])->where('is_active',0)->count());
        $this->assertSame(1,DB::table('finance_posting_lines')
            ->where('posting_batch_id',$created['posting_batch_id'])->where('is_active',0)->count());
        $this->assertSame(55.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$created['posting_batch_id'])->sum('debit'));
        $this->assertSame(55.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$created['posting_batch_id'])->sum('credit'));
        $this->assertCount(1,(new PlotEntryWriter())->receipt(1,$created['source_id'])['allocations']);
        // Reintroduce previously archived booking #31. The UNIQUE
        // (receipt_id, booking_id) constraint must reuse its original row.
        $archivedId=(int)DB::table('finance_plot_receipt_allocations')
            ->where('receipt_id',$created['source_id'])->where('booking_id',31)->value('id');
        $editor->receipt(1,$created['source_id'],[
            'customer_id'=>5,'receipt_number'=>'PR-COUNT-RESTORED','date'=>'2026-11-08',
            'amount'=>'80.00','cash_account_id'=>101,'narration'=>'Restore booking',
            'allocations'=>[
                ['booking_id'=>32,'plot_id'=>12,'receivable_account_id'=>103,'amount'=>'55.00'],
                ['booking_id'=>31,'plot_id'=>11,'receivable_account_id'=>102,'amount'=>'25.00'],
            ],
        ],7);
        $this->assertSame(2,DB::table('finance_plot_receipt_allocations')
            ->where('receipt_id',$created['source_id'])->where('is_active',1)->count());
        $this->assertSame(2,DB::table('finance_plot_receipt_allocations')
            ->where('receipt_id',$created['source_id'])->count());
        $this->assertSame($archivedId,(int)DB::table('finance_plot_receipt_allocations')
            ->where('receipt_id',$created['source_id'])->where('booking_id',31)->value('id'));
        $this->assertSame(80.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$created['posting_batch_id'])->sum('credit'));
        $this->assertDatabaseCount('finance_posting_batches',1);
        $this->assertDatabaseCount('daily_voucher_edit_snapshots',3);
        // Another Edit after archive + restore must resolve the booking by
        // its credit leg identity (posting IDs no longer sort like allocations).
        $editor->receipt(1,$created['source_id'],[
            'customer_id'=>5,'receipt_number'=>'PR-COUNT-RESTORED','date'=>'2026-11-08',
            'amount'=>'80.00','cash_account_id'=>101,'narration'=>'After restore',
            'allocations'=>[
                ['booking_id'=>31,'plot_id'=>11,'receivable_account_id'=>102,'amount'=>'30.00'],
                ['booking_id'=>32,'plot_id'=>12,'receivable_account_id'=>103,'amount'=>'50.00'],
            ],
        ],7);
        $this->assertSame(2,DB::table('finance_plot_receipt_allocations')
            ->where('receipt_id',$created['source_id'])->count());
        $this->assertSame(80.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$created['posting_batch_id'])->sum('credit'));
        $this->assertDatabaseCount('finance_posting_batches',1);
        $this->assertDatabaseCount('daily_voucher_edit_snapshots',4);
    }

    public function test_full_purchase_edit_rejects_booked_plot_without_mutating_financial_state(): void
    {
        $this->installFullPlotEditHistory();
        $created=(new PlotEntryWriter())->purchase(1,'PLOT-PURCHASE-BOOKING-EDIT-001',[
            'date'=>'2026-11-05','purchase_number'=>'PP-1','seller_account_id'=>102,
            'narration'=>'Original','items'=>[[
                'plot_code'=>'LAND-BOOKING-EDIT','description'=>'Original plot','area'=>'1',
                'unit'=>'Kanal','type'=>1,'road_id'=>1,'facing_id'=>1,
                'unit_rate'=>'100','inventory_account_id'=>104,
            ]],
        ],7);
        $plotId=(int)DB::table('finance_plot_purchase_items')
            ->where('purchase_id',$created['source_id'])->value('legacy_plot_id');
        DB::table('bookings')->insert(['id'=>55,'project_id'=>1,'customer_id'=>5,
            'plot_id'=>$plotId,'status'=>'active','cancel_status'=>'0']);
        try {
            (new \App\Services\Finance\PlotVoucherEditor())->purchase(1,$created['source_id'],[
                'date'=>'2026-11-06','purchase_number'=>'PP-ILLEGAL-EDIT','seller_account_id'=>102,
                'narration'=>'Should rollback','items'=>[[
                    'legacy_plot_id'=>$plotId,'plot_code'=>'LAND-BOOKING-NEW','description'=>'No write','area'=>'2',
                    'unit'=>'Kanal','type'=>1,'road_id'=>1,'facing_id'=>1,
                    'unit_rate'=>'300','inventory_account_id'=>104,
                ]],
            ],7);
            $this->fail('Booked purchased plot must not be financially edited.');
        } catch (\DomainException $error) {
            $this->assertStringContainsString('bookings',$error->getMessage());
        }
        $this->assertDatabaseHas('plots',['id'=>$plotId,'name'=>'LAND-BOOKING-EDIT']);
        $this->assertDatabaseHas('finance_plot_purchases',['id'=>$created['source_id'],
            'purchase_number'=>'PP-1','amount'=>100]);
        $this->assertSame(100.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$created['posting_batch_id'])->sum('debit'));
        $this->assertDatabaseCount('daily_voucher_edit_snapshots',0);
    }

    public function test_full_receipt_edit_rejects_other_project_without_changing_posting(): void
    {
        $this->installFullPlotEditHistory();
        $created=(new PlotEntryWriter())->receive(1,5,'PLOT-RECEIPT-CROSS-EDIT-001',[
            'date'=>'2026-11-05','receipt_number'=>'PPR-1','amount'=>'70.00',
            'cash_account_id'=>101,'narration'=>'Before',
            'allocations'=>[['booking_id'=>31,'plot_id'=>11,'receivable_account_id'=>102,'amount'=>'70.00']],
        ],7);
        try {
            (new \App\Services\Finance\PlotVoucherEditor())->receipt(2,$created['source_id'],[
                'customer_id'=>6,'receipt_number'=>'PR-SCOPED-2','date'=>'2026-11-06',
                'amount'=>'90','cash_account_id'=>201,'narration'=>'Forbidden',
                'allocations'=>[['booking_id'=>41,'plot_id'=>21,'receivable_account_id'=>201,'amount'=>'90']],
            ],7);
            $this->fail('Other projects must not edit this receipt.');
        } catch (\DomainException $error) {
            $this->assertStringContainsString('not found',$error->getMessage());
        }
        $this->assertDatabaseHas('finance_plot_receipts',['id'=>$created['source_id'],
            'receipt_number'=>'PPR-1','amount'=>70]);
        $this->assertDatabaseCount('daily_voucher_edit_snapshots',0);
        $this->assertDatabaseCount('finance_posting_batches',1);
    }

    public function test_plot_receipt_delete_archives_source_and_offsets_balance_without_erasing_evidence(): void
    {
        Schema::table('finance_plot_receipts',function(Blueprint $t): void {
            $t->boolean('is_active')->default(true);$t->softDeletes();
        });
        $created=(new PlotEntryWriter())->receive(1,5,'PLOT-RECEIPT-DELETE-001',[
            'date'=>'2026-11-05','receipt_number'=>'PPR-1','amount'=>'75.00',
            'cash_account_id'=>101,'narration'=>'Disposable test',
            'allocations'=>[['booking_id'=>31,'plot_id'=>11,'receivable_account_id'=>102,'amount'=>'75.00']],
        ],7);
        $batch=(new \App\Services\Finance\PlotEntryLifecycleService())->archive(
            1,'plot_receipt',$created['source_id'],'PLOT-RECEIPT-ARCHIVE-KEY-001',
            '2026-11-18','Test logical delete',7
        );
        $this->assertSame($created['posting_batch_id'],
            (int)DB::table('finance_posting_batches')->where('id',$batch)->value('reversal_of_batch_id'));
        $this->assertSame(0,(int)DB::table('finance_plot_receipts')
            ->where('id',$created['source_id'])->value('is_active'));
        $this->assertNotNull(DB::table('finance_plot_receipts')
            ->where('id',$created['source_id'])->value('deleted_at'));
        $this->assertDatabaseCount('finance_plot_receipts',1);
        $this->assertDatabaseCount('finance_plot_receipt_allocations',1);
        $this->assertDatabaseCount('finance_posting_batches',2);
        $this->assertSame(75.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$created['posting_batch_id'])->sum('debit'));
        $this->assertSame(75.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->sum('credit'));
        try{
            (new \App\Services\Finance\PlotEntryLifecycleService())->archive(
                1,'plot_receipt',$created['source_id'],'PLOT-RECEIPT-ARCHIVE-KEY-002',
                '2026-11-18','Second attempt',7
            );
            $this->fail('Already archived receipt must not generate a new posting.');
        }catch(\DomainException $error){
            $this->assertStringContainsString('deleted',$error->getMessage());
        }
        $this->assertDatabaseCount('finance_posting_batches',2);
    }

    public function test_plot_purchase_delete_archives_original_and_its_unbooked_inventory(): void
    {
        Schema::table('finance_plot_purchases',function(Blueprint $t): void {
            $t->boolean('is_active')->default(true);$t->softDeletes();
        });
        $created=(new PlotEntryWriter())->purchase(1,'PLOT-PURCHASE-DELETE-001',[
            'date'=>'2026-11-05','purchase_number'=>'PP-1','seller_account_id'=>102,
            'narration'=>'Disposable plot','items'=>[[
                'plot_code'=>'LAND-DELETE-1','description'=>'Test plot','area'=>'1',
                'unit'=>'Kanal','type'=>1,'road_id'=>1,'facing_id'=>1,
                'unit_rate'=>'100','inventory_account_id'=>104,
            ]],
        ],7);
        $plotId=(int)DB::table('finance_plot_purchase_items')->where('purchase_id',$created['source_id'])
            ->value('legacy_plot_id');
        $batch=(new \App\Services\Finance\PlotEntryLifecycleService())->archive(
            1,'plot_purchase',$created['source_id'],'PLOT-PURCHASE-ARCHIVE-KEY-001',
            '2026-11-18','Test logical purchase delete',7
        );
        $this->assertSame(0,(int)DB::table('plots')->where('id',$plotId)->value('is_active'));
        $this->assertSame(0,(int)DB::table('finance_plot_purchases')
            ->where('id',$created['source_id'])->value('is_active'));
        $this->assertDatabaseCount('finance_plot_purchase_items',1);
        $this->assertDatabaseCount('finance_posting_batches',2);
        $this->assertSame(100.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->sum('debit'));
        $this->assertSame(100.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$batch)->sum('credit'));
        // Reconciliation is for live inventory only; archival evidence and
        // reversal entries remain in storage but are not active purchases.
        $this->assertSame([], (new \App\Services\Finance\PlotPurchaseReconciliation())->audit(1));
    }

    public function test_full_purchase_edit_adds_then_archives_plots_and_preserves_balanced_postings(): void
    {
        $this->installFullPlotEditHistory();
        $writer=new PlotEntryWriter();
        $created=$writer->purchase(1,'PLOT-PURCHASE-DYNAMIC-EDIT-001',[
            'date'=>'2026-11-05','purchase_number'=>'PP-1','seller_account_id'=>102,
            'narration'=>'Before','items'=>[[
                'plot_code'=>'LAND-DYN-OLD','description'=>'Old plot','area'=>'1',
                'unit'=>'Kanal','type'=>1,'road_id'=>1,'facing_id'=>1,
                'unit_rate'=>'100','inventory_account_id'=>104,
            ]],
        ],7);
        $oldId=(int)DB::table('finance_plot_purchase_items')->where('purchase_id',$created['source_id'])
            ->value('legacy_plot_id');
        $editor=new \App\Services\Finance\PlotVoucherEditor();
        $editor->purchase(1,$created['source_id'],[
            'date'=>'2026-11-06','purchase_number'=>'PP-DYNAMIC-2','seller_account_id'=>102,
            'narration'=>'Two plots','items'=>[
                ['legacy_plot_id'=>$oldId,'plot_code'=>'LAND-DYN-OLD','description'=>'Updated',
                    'area'=>'1','unit'=>'Kanal','type'=>1,'road_id'=>1,'facing_id'=>1,
                    'unit_rate'=>'120','inventory_account_id'=>104],
                ['legacy_plot_id'=>'','plot_code'=>'LAND-DYN-NEW','description'=>'New plot',
                    'area'=>'1','unit'=>'Kanal','type'=>1,'road_id'=>1,'facing_id'=>1,
                    'unit_rate'=>'80','inventory_account_id'=>104],
            ],
        ],7);
        $this->assertSame(2,DB::table('finance_plot_purchase_items')
            ->where('purchase_id',$created['source_id'])->where('is_active',1)->count());
        $this->assertSame(200.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$created['posting_batch_id'])->sum('debit'));
        $newId=(int)DB::table('finance_plot_purchase_items')->where('purchase_id',$created['source_id'])
            ->where('plot_code','LAND-DYN-NEW')->value('legacy_plot_id');
        $this->assertGreaterThan(0,$newId);
        $editor->purchase(1,$created['source_id'],[
            'date'=>'2026-11-07','purchase_number'=>'PP-DYNAMIC-3','seller_account_id'=>102,
            'narration'=>'One remaining plot','items'=>[[
                'legacy_plot_id'=>$newId,'plot_code'=>'LAND-DYN-NEW','description'=>'Retained',
                'area'=>'1','unit'=>'Kanal','type'=>1,'road_id'=>1,'facing_id'=>1,
                'unit_rate'=>'90','inventory_account_id'=>104,
            ]],
        ],7);
        $this->assertSame(1,DB::table('finance_plot_purchase_items')
            ->where('purchase_id',$created['source_id'])->where('is_active',1)->count());
        $this->assertSame(1,DB::table('finance_plot_purchase_items')
            ->where('purchase_id',$created['source_id'])->where('is_active',0)->count());
        $this->assertSame(0,(int)DB::table('plots')->where('id',$oldId)->value('is_active'));
        $this->assertSame(1,(int)DB::table('plots')->where('id',$newId)->value('is_active'));
        $this->assertSame(90.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$created['posting_batch_id'])->sum('debit'));
        $this->assertSame(90.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$created['posting_batch_id'])->sum('credit'));
        $this->assertCount(1,$writer->purchaseDetails(1,$created['source_id'])['items']);
        $reconciliation=(new \App\Services\Finance\PlotPurchaseReconciliation())->audit(1);
        $this->assertTrue($reconciliation[0]['ok'],implode(',',$reconciliation[0]['issues']));
        $this->assertDatabaseCount('finance_posting_batches',1);
        $this->assertDatabaseCount('daily_voucher_edit_snapshots',2);
        // Further Edit after the first item was archived must not reuse
        // its old debit leg or create another inventory record.
        $editor->purchase(1,$created['source_id'],[
            'date'=>'2026-11-08','purchase_number'=>'PP-DYNAMIC-3','seller_account_id'=>102,
            'narration'=>'Still one plot','items'=>[[
                'legacy_plot_id'=>$newId,'plot_code'=>'LAND-DYN-NEW','description'=>'Retained again',
                'area'=>'1','unit'=>'Kanal','type'=>1,'road_id'=>1,'facing_id'=>1,
                'unit_rate'=>'95','inventory_account_id'=>104,
            ]],
        ],7);
        $this->assertSame(2,DB::table('finance_plot_purchase_items')
            ->where('purchase_id',$created['source_id'])->count());
        $this->assertSame(95.0,(float)DB::table('finance_posting_lines')
            ->where('posting_batch_id',$created['posting_batch_id'])->sum('debit'));
        $this->assertDatabaseCount('finance_posting_batches',1);
    }

    public function test_posting_balance_command_defaults_to_all_projects_and_retains_optional_filter(): void
    {
        $created=(new PlotEntryWriter())->receive(1,5,'POSTING-AUDIT-ALL-PROJECTS-001',[
            'date'=>'2026-11-05','receipt_number'=>'PPR-1','amount'=>'70.00',
            'cash_account_id'=>101,'narration'=>'All-project audit',
            'allocations'=>[['booking_id'=>31,'plot_id'=>11,'receivable_account_id'=>102,
                'amount'=>'70.00']],
        ],7);
        $artisan=\Illuminate\Support\Facades\Artisan::class;
        $this->assertSame(0,$artisan::call('finance:audit-posting-balances',['--json'=>true]));
        $all=json_decode(trim($artisan::output()),true,512,JSON_THROW_ON_ERROR);
        $this->assertNull($all['project_id']);
        $this->assertSame(2,$all['project_count']);
        $this->assertSame(0,$all['finding_count']);

        DB::table('finance_posting_lines')->where('posting_batch_id',$created['posting_batch_id'])
            ->where('credit','>',0)->update(['credit'=>'71.00']);
        $this->assertSame(1,$artisan::call('finance:audit-posting-balances',['--json'=>true]));
        $all=json_decode(trim($artisan::output()),true,512,JSON_THROW_ON_ERROR);
        $this->assertSame(1,$all['finding_count']);
        $this->assertSame(1,$all['findings'][0]['project_id']);
        $this->assertContains('POSTING_UNBALANCED',$all['findings'][0]['issues']);

        $this->assertSame(0,$artisan::call('finance:audit-posting-balances',
            ['--project'=>2,'--json'=>true]));
        $filtered=json_decode(trim($artisan::output()),true,512,JSON_THROW_ON_ERROR);
        $this->assertSame(2,$filtered['project_id']);
        $this->assertSame(1,$filtered['project_count']);
        $this->assertSame(0,$filtered['finding_count']);
        $this->assertSame(1,$artisan::call('finance:audit-posting-balances',
            ['--project'=>'not-a-number']));
    }

    public function test_shared_posting_balance_audit_checks_all_series_and_ignores_archived_legs(): void
    {
        $receipt=(new PlotEntryWriter())->receive(1,5,'POSTING-AUDIT-RECEIPT-001',[
            'date'=>'2026-11-05','receipt_number'=>'PPR-1','amount'=>'70.00',
            'cash_account_id'=>101,'narration'=>'Audit receipt',
            'allocations'=>[['booking_id'=>31,'plot_id'=>11,'receivable_account_id'=>102,
                'amount'=>'70.00']],
        ],7);
        $purchase=(new PlotEntryWriter())->purchase(1,'POSTING-AUDIT-PURCHASE-001',[
            'date'=>'2026-11-05','purchase_number'=>'PP-1','seller_account_id'=>102,
            'narration'=>'Audit purchase','items'=>[[
                'plot_code'=>'AUDIT-PLOT-ONE','description'=>'Audit',
                'area'=>'1','unit'=>'Kanal','type'=>1,'road_id'=>1,'facing_id'=>1,
                'unit_rate'=>'100','inventory_account_id'=>104,
            ]],
        ],7);
        $audit=new \App\Services\Finance\PostingBatchIntegrityAudit();
        $this->assertSame([],$audit->audit(1));
        DB::table('finance_posting_lines')
            ->where('posting_batch_id',$purchase['posting_batch_id'])
            ->where('credit','>',0)->update(['credit'=>'101.00']);
        $findings=$audit->audit(1);
        $this->assertCount(1,$findings);
        $this->assertSame($purchase['posting_batch_id'],$findings[0]['batch_id']);
        $this->assertContains('POSTING_UNBALANCED',$findings[0]['issues']);
        DB::table('finance_posting_lines')
            ->where('posting_batch_id',$purchase['posting_batch_id'])
            ->where('credit','>',0)->update(['credit'=>'100.00']);
        $this->assertSame([],$audit->audit(1));
        // A REV-labelled posting without an original is not a valid
        // accounting reversal, even when its debit/credit totals balance.
        DB::table('finance_posting_batches')->where('id',$receipt['posting_batch_id'])
            ->update(['voucher_series'=>'REV']);
        $reversalFindings=$audit->audit(1);
        $this->assertCount(1,$reversalFindings);
        $this->assertContains('REVERSAL_ORIGINAL_MISSING',$reversalFindings[0]['issues']);
        DB::table('finance_posting_batches')->where('id',$receipt['posting_batch_id'])
            ->update(['voucher_series'=>'PR']);
        $this->assertSame([],$audit->audit(1));
        DB::table('finance_posting_lines')
            ->where('posting_batch_id',$receipt['posting_batch_id'])
            ->where('credit','>',0)->update(['is_active'=>0]);
        $findings=$audit->audit(1);
        $this->assertCount(1,$findings);
        $this->assertSame($receipt['posting_batch_id'],$findings[0]['batch_id']);
        $this->assertContains('ACTIVE_LINES_MISSING',$findings[0]['issues']);
        $this->assertContains('POSTING_UNBALANCED',$findings[0]['issues']);
        $this->assertSame([],$audit->audit(2));
        $this->assertDatabaseCount('finance_posting_batches',2);
    }

}
