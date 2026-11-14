<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\V2PlotEntryWriter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class V2PlotEntryWriterTest extends TestCase
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
        (require base_path('database/v2_migrations/2026_10_30_000100_create_v2_finance_posting_core.php'))->up();
        (require base_path('database/v2_migrations/2026_11_05_000300_create_v2_plot_receipt_purchase.php'))->up();
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
        $writer = new V2PlotEntryWriter();
        $data = ['date' => '2026-11-05', 'receipt_number' => 'PR-TEST-1', 'amount' => '100.00',
            'cash_account_id' => 101, 'narration' => 'Two plot payment', 'allocations' => [
                ['booking_id' => 31, 'plot_id' => 11, 'receivable_account_id' => 102, 'amount' => '60.00'],
                ['booking_id' => 32, 'plot_id' => 12, 'receivable_account_id' => 103, 'amount' => '40.00'],
            ]];
        $created = $writer->receive(1, 5, 'PLOT-RECEIPT-REQUEST-0001', $data, 7);
        $replayed = $writer->receive(1, 5, 'PLOT-RECEIPT-REQUEST-0001', $data, 7);
        $this->assertFalse($created['replayed']); $this->assertTrue($replayed['replayed']);
        $this->assertSame($created['source_id'], $replayed['source_id']);
        $this->assertDatabaseCount('finance_plot_receipts', 1);
        $this->assertDatabaseCount('finance_plot_receipt_allocations', 2);
        $this->assertSame(100.0, (float) DB::table('finance_posting_lines')->where('posting_batch_id', $created['posting_batch_id'])->where('project_head_subhead_id', 101)->value('debit'));
        $details = $writer->receipt(1, $created['source_id']);
        $this->assertSame(['A-11', 'A-12'], $details['allocations']->pluck('plot_name')->all());
        $register = (new \App\Services\Finance\V2PostingGateway())->voucherRegister(1);
        $this->assertSame('PR-TEST-1', collect($register)->firstWhere('source_kind', 'plot_receipt')['source_reference']);
    }

    public function test_single_plot_receipt_posts_balanced_and_details_are_project_scoped(): void
    {
        $writer = new V2PlotEntryWriter();
        $payload = [
            'date' => '2026-11-14',
            'receipt_number' => 'PR-SINGLE-PLOT',
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
        $writer = new V2PlotEntryWriter();
        try {
            $writer->receive(1, 5, 'PLOT-ZERO-REQUEST-00001', [
                'date' => '2026-11-14', 'receipt_number' => 'PR-EMPTY',
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
        $writer = new V2PlotEntryWriter();
        $data = ['date' => '2026-11-05', 'purchase_number' => 'PP-TEST-1', 'seller_account_id' => 102,
            'narration' => 'Land purchase', 'items' => [
                ['plot_code' => 'LAND-1', 'description' => 'Parcel one', 'area' => '1.2500', 'unit' => 'Kanal', 'type' => 1, 'road_id' => 1, 'facing_id' => 1, 'is_corner' => false, 'unit_rate' => '100.00', 'inventory_account_id' => 104],
            ]];
        $created = $writer->purchase(1, 'PLOT-PURCHASE-REQUEST-0001', $data, 7);
        $again = $writer->purchase(1, 'PLOT-PURCHASE-REQUEST-0001', $data, 7);
        $this->assertFalse($created['replayed']); $this->assertTrue($again['replayed']);
        $lines = DB::table('finance_posting_lines')->where('posting_batch_id', $created['posting_batch_id'])->get()->keyBy('project_head_subhead_id');
        $this->assertSame(125.0, (float) $lines[104]->debit);
        $this->assertSame(125.0, (float) $lines[102]->credit);
        $this->assertSame('PP-TEST-1', collect((new \App\Services\Finance\V2PostingGateway())->voucherRegister(1))
            ->firstWhere('source_kind', 'plot_purchase')['source_reference']);
        $this->assertEquals(1.25, (float) $writer->purchaseDetails(1, $created['source_id'])['items'][0]->area);
        $legacyPlotId = (int) $writer->purchaseDetails(1, $created['source_id'])['items'][0]->legacy_plot_id;
        $this->assertSame(1, (int) DB::table('plots')->where('id', $legacyPlotId)->value('is_active'));
        $this->assertSame(0, (int) DB::table('plots')->where('id', $legacyPlotId)->value('sold'));
        $this->assertSame(0.0, (float) DB::table('plots')->where('id', $legacyPlotId)->value('amount'));
    }

    public function test_foreign_project_plot_booking_and_unallocated_amounts_fail_without_writes(): void
    {
        $writer = new V2PlotEntryWriter();
        $data = ['date' => '2026-11-05', 'receipt_number' => 'PR-TEST-2', 'amount' => '100.00', 'cash_account_id' => 101,
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
        $data = ['date' => '2026-11-05', 'receipt_number' => 'PR-DUP-PLOT', 'amount' => '100.00', 'cash_account_id' => 101,
            'narration' => 'Duplicate plot must not post', 'allocations' => [
                ['booking_id' => 31, 'plot_id' => 11, 'receivable_account_id' => 102, 'amount' => '60.00'],
                ['booking_id' => 31, 'plot_id' => 11, 'receivable_account_id' => 102, 'amount' => '40.00'],
            ]];
        try {
            (new V2PlotEntryWriter())->receive(1, 5, 'PLOT-RECEIPT-DUPLICATE-PLOT', $data, 7);
            $this->fail('A single plot cannot appear twice in a multi-plot receipt.');
        } catch (\DomainException $error) {
            $this->assertSame('V2_PLOT_ALLOCATION_INVALID', $error->getMessage());
        }
        $this->assertDatabaseCount('finance_plot_receipts', 0);
        $this->assertDatabaseCount('finance_posting_batches', 0);
    }

    public function test_plot_purchase_cancellation_is_append_only_and_reverses_exact_posting(): void
    {
        $writer = new V2PlotEntryWriter();
        $created = $writer->purchase(1, 'PLOT-PURCHASE-REQUEST-REV1', [
            'date' => '2026-11-05', 'purchase_number' => 'PP-REV-1', 'seller_account_id' => 102,
            'narration' => 'Reversible purchase', 'items' => [[
                'plot_code' => 'LAND-R1', 'description' => 'Parcel reversal test', 'area' => '1',
                'unit' => 'Kanal', 'type' => 1, 'road_id' => 1, 'facing_id' => 1,
                'unit_rate' => '100', 'inventory_account_id' => 104,
            ]],
        ], 7);
        $reversal = (new \App\Services\Finance\V2PlotEntryLifecycleService())->reverse(
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
        $writer = new V2PlotEntryWriter();
        $created = $writer->purchase(1, 'PLOT-PURCHASE-REQUEST-BOOKED', [
            'date' => '2026-11-05', 'purchase_number' => 'PP-BOOKED-1', 'seller_account_id' => 102,
            'narration' => 'Booked purchase', 'items' => [[
                'plot_code' => 'LAND-BOOKED', 'description' => 'Booked parcel', 'area' => '1',
                'unit' => 'Kanal', 'type' => 1, 'road_id' => 1, 'facing_id' => 1,
                'unit_rate' => '100', 'inventory_account_id' => 104,
            ]],
        ], 7);
        $plotId = (int) DB::table('finance_plot_purchase_items')->where('purchase_id', $created['source_id'])->value('legacy_plot_id');
        DB::table('bookings')->insert(['id' => 51, 'project_id' => 1, 'customer_id' => 5, 'plot_id' => $plotId, 'status' => 'active', 'cancel_status' => '0']);
        try {
            (new \App\Services\Finance\V2PlotEntryLifecycleService())->reverse(
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
        $pilot = require base_path('database/local_pilot_migrations/2026_11_14_000100_create_v2_plot_receipt_storage.php');
        $pilot->up();
        $pilot->up(); // Running again must not overwrite receipt evidence.
        $this->assertTrue(Schema::hasTable('finance_plot_receipts'));
        $this->assertTrue(Schema::hasTable('finance_plot_receipt_allocations'));
        $this->assertTrue(Schema::hasTable('finance_plot_receipt_postings'));
        $writer = new V2PlotEntryWriter();
        $payload = ['date' => '2026-11-05', 'receipt_number' => 'PR-LOCAL-GATE-1',
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
        $repair = require base_path('database/local_pilot_migrations/2026_11_14_000101_repair_v2_plot_receipt_claim_fk.php');
        try {
            $repair->up();
            $this->fail('MySQL claim recovery must not execute on the SQLite fixture.');
        } catch (\RuntimeException $error) {
            $this->assertSame('V2_PLOT_RECEIPT_FK_REPAIR_MYSQL_ONLY', $error->getMessage());
        }
        $this->assertSame($before, DB::table('finance_posting_batches')->count());
        $this->assertTrue(Schema::hasTable('finance_plot_receipt_postings'));
    }

    public function test_phase07_mysql_claim_fk_repair_refuses_production_environment(): void
    {
        $repair = require base_path('database/local_pilot_migrations/2026_11_14_000101_repair_v2_plot_receipt_claim_fk.php');
        config()->set('app.env', 'production');
        try {
            $repair->up();
            $this->fail('FK repair must be limited to isolated local testing.');
        } catch (\RuntimeException $error) {
            $this->assertSame('V2_ISOLATED_CONNECTION_REQUIRED', $error->getMessage());
        } finally {
            config()->set('app.env', 'testing');
        }
    }

    public function test_phase07_original_fk_has_safe_mysql_identifier_and_audit_checks_integrity(): void
    {
        $migration = file_get_contents(base_path('database/local_pilot_migrations/2026_11_14_000100_create_v2_plot_receipt_storage.php'));
        $repair = file_get_contents(base_path('database/local_pilot_migrations/2026_11_14_000101_repair_v2_plot_receipt_claim_fk.php'));
        $audit = file_get_contents(app_path('Console/Commands/AuditV2PlotReceiptReadiness.php'));
        $this->assertLessThanOrEqual(64, strlen('v2_plot_rcpt_post_claim_fk'));
        $this->assertStringContainsString("'v2_plot_rcpt_post_claim_fk'", $migration);
        $this->assertStringContainsString("'v2_plot_rcpt_post_claim_fk'", $repair);
        $this->assertStringContainsString('V2_PLOT_RECEIPT_INCOMPLETE_FK', $audit);
        $this->assertStringContainsString('information_schema.KEY_COLUMN_USAGE', $audit);
    }

    public function test_phase08_local_migration_installs_purchase_only_and_writer_posts_balanced_inventory_cost(): void
    {
        Schema::drop('finance_plot_purchase_postings');
        Schema::drop('finance_plot_purchase_items');
        Schema::drop('finance_plot_purchases');

        $receiptCount = DB::table('finance_plot_receipts')->count();
        $pilot = require base_path('database/local_pilot_migrations/2026_11_14_000200_create_v2_plot_purchase_storage.php');
        $pilot->up();
        $pilot->up(); // A repeat must not wipe already installed storage.
        $this->assertSame($receiptCount, DB::table('finance_plot_receipts')->count());
        $this->assertTrue(Schema::hasTable('finance_plot_purchases'));
        $this->assertTrue(Schema::hasTable('finance_plot_purchase_items'));
        $this->assertTrue(Schema::hasTable('finance_plot_purchase_postings'));

        $created = (new V2PlotEntryWriter())->purchase(1, 'PLOT-PURCHASE-PHASE08-TEST-0001', [
            'date' => '2026-11-14', 'purchase_number' => 'PP-PHASE08-001',
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
        $pilot = require base_path('database/local_pilot_migrations/2026_11_14_000200_create_v2_plot_purchase_storage.php');
        try {
            $pilot->up();
            $this->fail('Partially installed purchase storage must stop rather than rebuilding.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('V2_PLOT_PURCHASE_PARTIAL_STORAGE', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('finance_plot_purchases'));
        $this->assertTrue(Schema::hasTable('finance_plot_purchase_items'));
    }

    public function test_phase08_local_migration_refuses_non_isolated_production_environment(): void
    {
        $pilot = require base_path('database/local_pilot_migrations/2026_11_14_000200_create_v2_plot_purchase_storage.php');
        config()->set('app.env', 'production');
        try {
            $pilot->up();
            $this->fail('Plot Purchase migration must not run in production.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('V2_ISOLATED_CONNECTION_REQUIRED', $exception->getMessage());
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

    public function test_phase07_local_migration_fails_closed_outside_isolated_testing(): void
    {
        $pilot = require base_path('database/local_pilot_migrations/2026_11_14_000100_create_v2_plot_receipt_storage.php');
        config()->set('app.env', 'production');
        try {
            $pilot->up();
            $this->fail('Local plot receipt migration must never run in a production environment.');
        } catch (\RuntimeException $error) {
            $this->assertSame('V2_ISOLATED_CONNECTION_REQUIRED', $error->getMessage());
        } finally {
            config()->set('app.env', 'testing');
        }
        $this->assertTrue(Schema::hasTable('finance_plot_receipts'));
    }

    public function test_phase07_local_migration_rejects_partially_existing_receipt_storage(): void
    {
        Schema::drop('finance_plot_receipt_postings');
        $pilot = require base_path('database/local_pilot_migrations/2026_11_14_000100_create_v2_plot_receipt_storage.php');
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

}
