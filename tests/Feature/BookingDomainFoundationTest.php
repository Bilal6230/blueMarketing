<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Services\Booking\BookingEditLifecycleInspector;
use App\Services\Booking\BookingPriceCalculator;
use App\Services\Booking\BookingSalesAccountingResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingDomainFoundationTest extends TestCase
{
    private Booking $booking;
    private int $voucherId;
    private int $customerPivotId;
    private int $totalSalePivotId;
    private int $customerLedgerId;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
        $this->seedPristineBooking();
    }

    public function test_pristine_pending_booking_is_eligible_and_balanced(): void
    {
        $result = $this->inspect();

        $this->assertTrue($result['pricing_edit_allowed']);
        $this->assertFalse($result['has_accounting_ambiguity']);
        $this->assertTrue($result['amounts_consistent']);
        $this->assertSame('pending', $result['original_voucher_status']);
        $this->assertSame([], $result['pricing_block_reasons']);
    }

    public function test_schedule_blocks_pricing_and_reports_count(): void
    {
        DB::table('booking_details')->insert(['booking_id' => $this->booking->id, 'amount' => 100, 'due_date' => '2026-10-01']);
        $result = $this->inspect();

        $this->assertTrue($result['has_schedule']);
        $this->assertSame(1, $result['schedule_row_count']);
        $this->assertContains('SCHEDULE_EXISTS', $result['pricing_block_reasons']);
    }

    public function test_primary_and_legacy_ppr_evidence_block_pricing(): void
    {
        DB::table('booking_vouchers')->insert(['booking_id' => $this->booking->id, 'voucher_series' => 'PPR']);
        $this->assertContains('PAYMENT_ACTIVITY_EXISTS', $this->inspect()['pricing_block_reasons']);

        DB::table('booking_vouchers')->delete();
        DB::table('customer_ledger')->insert([
            'customer_id' => $this->booking->customer_id,
            'project_id' => $this->booking->project_id,
            'plot_id' => $this->booking->plot_id,
            'transaction_type' => 'PPR',
            'amount_in' => 0,
            'amount_out' => 100,
        ]);
        $this->assertTrue($this->inspect()['has_payment_activity']);
    }

    public function test_transfer_and_real_resale_marker_are_detected(): void
    {
        DB::table('journal_vouchers')->insert($this->voucherRow('SV-2', 'TRANSFER-' . $this->booking->id));
        DB::table('ledgers')->insert([
            'type' => 'SV', 'type_id' => 999, 'voucher_number' => 'SV-999',
            'project_head_subheads_id' => $this->totalSalePivotId, 'reference' => $this->booking->id,
            'amount_in' => 1, 'amount_out' => 0, 'detail' => 'RESALE_PROFIT#' . $this->booking->id . ' marker',
        ]);
        $result = $this->inspect();

        $this->assertTrue($result['has_transfer_history']);
        $this->assertTrue($result['has_resale_history']);
        $this->assertContains('TRANSFER_HISTORY_EXISTS', $result['pricing_block_reasons']);
        $this->assertContains('RESALE_HISTORY_EXISTS', $result['pricing_block_reasons']);
    }

    public function test_unexpected_non_resale_voucher_line_blocks_without_resale_history(): void
    {
        DB::table('journal_voucher_details')->insert([
            'journal_voucher_id' => $this->voucherId, 'account_id' => 999,
            'debit' => 1, 'credit' => 0, 'description' => 'unexpected non-resale line',
        ]);

        $result = $this->inspect();

        $this->assertFalse($result['pricing_edit_allowed']);
        $this->assertFalse($result['has_resale_history']);
        $this->assertContains('UNEXPECTED_VOUCHER_LINES', $result['pricing_block_reasons']);
        $this->assertNotContains('RESALE_HISTORY_EXISTS', $result['pricing_block_reasons']);
    }

    public function test_cancelled_and_deleted_bookings_are_blocked_but_metadata_is_separate(): void
    {
        $this->booking->update(['cancel_status' => '1']);
        $result = $this->inspect($this->booking->fresh());
        $this->assertContains('BOOKING_CANCELLED', $result['pricing_block_reasons']);
        $this->assertFalse($result['metadata_edit_allowed']);

        $this->booking->update(['cancel_status' => '0']);
        $this->booking->delete();
        $deleted = Booking::withTrashed()->findOrFail($this->booking->id);
        $result = $this->inspect($deleted);
        $this->assertContains('BOOKING_DELETED', $result['pricing_block_reasons']);
    }

    public function test_non_pending_voucher_statuses_block_pricing(): void
    {
        foreach (['approved', 'rejected'] as $status) {
            DB::table('journal_vouchers')->where('id', $this->voucherId)->update(['status' => $status]);
            $result = $this->inspect();
            $this->assertSame($status, $result['original_voucher_status']);
            $this->assertContains('VOUCHER_NOT_PENDING', $result['pricing_block_reasons']);
        }
    }

    public function test_missing_and_duplicate_original_voucher_are_diagnostic(): void
    {
        DB::table('journal_vouchers')->where('id', $this->voucherId)->delete();
        $this->assertContains('ORIGINAL_VOUCHER_MISSING', $this->resolve()->blockReasons);

        $this->voucherId = DB::table('journal_vouchers')->insertGetId($this->voucherRow('SV-10', 'BOOKING-' . $this->booking->id));
        DB::table('journal_vouchers')->insert($this->voucherRow('SV-11', 'BOOKING-' . $this->booking->id));
        $this->assertContains('ORIGINAL_VOUCHER_DUPLICATE', $this->resolve()->blockReasons);
    }

    public function test_missing_and_duplicate_customer_pivot_are_diagnostic(): void
    {
        DB::table('project_head_subheads')->where('id', $this->customerPivotId)->delete();
        $this->assertContains('CUSTOMER_PIVOT_MISSING', $this->resolve()->blockReasons);

        $this->customerPivotId = DB::table('project_head_subheads')->insertGetId($this->customerPivotRow());
        DB::table('project_head_subheads')->insert($this->customerPivotRow());
        $this->assertContains('CUSTOMER_PIVOT_AMBIGUOUS', $this->resolve()->blockReasons);
    }

    public function test_missing_and_duplicate_total_sale_pivot_are_diagnostic(): void
    {
        DB::table('project_head_subheads')->where('id', $this->totalSalePivotId)->delete();
        $this->assertContains('TOTAL_SALE_PIVOT_MISSING', $this->resolve()->blockReasons);

        $this->totalSalePivotId = DB::table('project_head_subheads')->insertGetId($this->totalSalePivotRow());
        DB::table('project_head_subheads')->insert($this->totalSalePivotRow());
        $this->assertContains('TOTAL_SALE_PIVOT_AMBIGUOUS', $this->resolve()->blockReasons);
    }

    public function test_missing_and_duplicate_principal_details_are_diagnostic(): void
    {
        DB::table('journal_voucher_details')->where('account_id', $this->customerPivotId)->delete();
        $this->assertContains('CUSTOMER_DETAIL_MISSING', $this->resolve()->blockReasons);

        DB::table('journal_voucher_details')->insert($this->customerDetailRow());
        DB::table('journal_voucher_details')->insert($this->customerDetailRow());
        $this->assertContains('CUSTOMER_DETAIL_AMBIGUOUS', $this->resolve()->blockReasons);

        DB::table('journal_voucher_details')->where('account_id', $this->totalSalePivotId)->delete();
        $this->assertContains('TOTAL_SALE_DETAIL_MISSING', $this->resolve()->blockReasons);
    }

    public function test_duplicate_total_sale_detail_is_diagnostic(): void
    {
        DB::table('journal_voucher_details')->insert($this->totalSaleDetailRow());

        $this->assertContains('TOTAL_SALE_DETAIL_AMBIGUOUS', $this->resolve()->blockReasons);
    }

    public function test_missing_and_duplicate_principal_ledgers_are_diagnostic(): void
    {
        DB::table('ledgers')->where('project_head_subheads_id', $this->customerPivotId)->delete();
        $this->assertContains('CUSTOMER_LEDGER_LINE_MISSING', $this->resolve()->blockReasons);

        DB::table('ledgers')->insert($this->customerLedgerLineRow());
        DB::table('ledgers')->insert($this->customerLedgerLineRow());
        $this->assertContains('CUSTOMER_LEDGER_LINE_AMBIGUOUS', $this->resolve()->blockReasons);

        DB::table('ledgers')->where('project_head_subheads_id', $this->totalSalePivotId)->delete();
        $this->assertContains('TOTAL_SALE_LEDGER_LINE_MISSING', $this->resolve()->blockReasons);
    }

    public function test_duplicate_total_sale_ledger_is_diagnostic(): void
    {
        DB::table('ledgers')->insert($this->totalSaleLedgerLineRow());

        $this->assertContains('TOTAL_SALE_LEDGER_LINE_AMBIGUOUS', $this->resolve()->blockReasons);
    }

    public function test_invalid_linked_customer_ledger_is_diagnostic(): void
    {
        DB::table('customer_ledger')->where('id', $this->customerLedgerId)->update(['transaction_type' => 'PPR']);
        $this->assertContains('INITIAL_CUSTOMER_LEDGER_INVALID', $this->resolve()->blockReasons);
    }

    public function test_unexpected_and_unbalanced_lines_block_pricing(): void
    {
        DB::table('journal_voucher_details')->insert([
            'journal_voucher_id' => $this->voucherId, 'account_id' => 999,
            'debit' => 1, 'credit' => 0, 'description' => 'unexpected',
        ]);
        $result = $this->resolve();

        $this->assertContains('UNEXPECTED_VOUCHER_LINES', $result->blockReasons);
        $this->assertContains('VOUCHER_UNBALANCED', $result->blockReasons);
    }

    public function test_stale_amounts_are_reported_without_becoming_structural_ambiguity(): void
    {
        DB::table('bookings')->where('id', $this->booking->id)->update(['total_price' => 4900000]);
        $result = app(BookingSalesAccountingResolver::class)->resolve($this->booking->fresh());

        $this->assertFalse($result->amountsConsistent);
        $this->assertTrue($result->isStructurallyValid());
        $this->assertSame('4900000.00', $result->data['booking_total']);
        $this->assertSame('5000000.00', $result->data['customer_ledger_total']);
    }

    public function test_calculation_and_inspection_are_strictly_read_only(): void
    {
        $tables = ['bookings', 'customer_ledger', 'journal_vouchers', 'journal_voucher_details', 'ledgers', 'project_head_subheads', 'booking_details', 'booking_vouchers'];
        $before = $this->snapshot($tables);

        app(BookingPriceCalculator::class)->calculate('10', '500000', 0, '0', 0, '0', '0');
        $this->resolve();
        $this->inspect();

        $this->assertSame($before, $this->snapshot($tables));
    }

    private function inspect(?Booking $booking = null): array
    {
        return app(BookingEditLifecycleInspector::class)->inspect($booking ?? $this->booking->fresh())->toArray();
    }

    private function resolve()
    {
        return app(BookingSalesAccountingResolver::class)->resolve($this->booking->fresh());
    }

    private function snapshot(array $tables): array
    {
        $snapshot = [];
        foreach ($tables as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }
        return $snapshot;
    }

    private function seedPristineBooking(): void
    {
        $this->booking = Booking::create([
            'project_id' => 10, 'customer_id' => 20, 'plot_id' => 30, 'plot_type' => 1,
            'plot_size' => '10', 'plot_rate' => 500000, 'is_corner' => 0, 'is_park' => 0,
            'park_facing' => 0, 'carner_price' => 0, 'dicount_value' => 0, 'total_price' => 5000000,
            'booking_date' => '2026-09-08', 'status' => 'active', 'user_id' => 1, 'cancel_status' => '0',
        ]);
        $this->customerPivotId = DB::table('project_head_subheads')->insertGetId($this->customerPivotRow());
        $this->totalSalePivotId = DB::table('project_head_subheads')->insertGetId($this->totalSalePivotRow());
        $this->voucherId = DB::table('journal_vouchers')->insertGetId($this->voucherRow('SV-1', 'BOOKING-' . $this->booking->id));
        $this->customerLedgerId = DB::table('customer_ledger')->insertGetId([
            'customer_id' => 20, 'project_id' => 10, 'plot_id' => 30, 'transaction_type' => 'Bo',
            'amount_in' => 5000000, 'amount_out' => 0,
        ]);
        DB::table('journal_voucher_details')->insert([$this->customerDetailRow(), $this->totalSaleDetailRow()]);
        DB::table('ledgers')->insert([$this->customerLedgerLineRow(), $this->totalSaleLedgerLineRow()]);
    }

    private function voucherRow(string $number, string $reference): array
    {
        return ['voucher_number' => $number, 'type' => 'SV', 'reference' => $reference, 'project_id' => 10, 'total_debit' => 5000000, 'total_credit' => 5000000, 'status' => 'pending'];
    }

    private function customerPivotRow(): array
    {
        return ['project_id' => 10, 'head_accounting_id' => 16, 'subhead_accounting_id' => 200, 'plot_id' => 30, 'customer_id' => 20];
    }

    private function totalSalePivotRow(): array
    {
        return ['project_id' => 10, 'head_accounting_id' => 16, 'subhead_accounting_id' => 123, 'plot_id' => null, 'customer_id' => null];
    }

    private function customerDetailRow(): array
    {
        return ['journal_voucher_id' => $this->voucherId, 'account_id' => $this->customerPivotId, 'debit' => 0, 'credit' => 5000000, 'description' => 'Booking principal'];
    }

    private function totalSaleDetailRow(): array
    {
        return ['journal_voucher_id' => $this->voucherId, 'account_id' => $this->totalSalePivotId, 'debit' => 5000000, 'credit' => 0, 'description' => 'Booking principal'];
    }

    private function customerLedgerLineRow(): array
    {
        return ['type' => 'SV', 'type_id' => $this->voucherId, 'voucher_number' => 'SV-1', 'project_head_subheads_id' => $this->customerPivotId, 'customer_ledger_id' => $this->customerLedgerId, 'reference' => $this->booking->id, 'amount_in' => 0, 'amount_out' => 5000000, 'detail' => 'Booking principal'];
    }

    private function totalSaleLedgerLineRow(): array
    {
        return ['type' => 'SV', 'type_id' => $this->voucherId, 'voucher_number' => 'SV-1', 'project_head_subheads_id' => $this->totalSalePivotId, 'customer_ledger_id' => $this->customerLedgerId, 'reference' => $this->booking->id, 'amount_in' => 5000000, 'amount_out' => 0, 'detail' => 'Booking principal'];
    }

    private function createSchema(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id(); $table->integer('project_id'); $table->integer('customer_id'); $table->integer('plot_id');
            $table->integer('plot_type'); $table->string('plot_size'); $table->decimal('plot_rate', 10, 2);
            $table->integer('is_corner'); $table->integer('is_park'); $table->decimal('park_facing', 10, 2);
            $table->decimal('carner_price', 10, 2); $table->decimal('dicount_value', 10, 2); $table->decimal('total_price', 10, 2);
            $table->timestamp('booking_date'); $table->string('status'); $table->integer('user_id'); $table->string('cancel_status');
            $table->timestamps(); $table->softDeletes();
        });
        Schema::create('project_head_subheads', function (Blueprint $table) {
            $table->id(); $table->integer('project_id'); $table->integer('head_accounting_id'); $table->integer('subhead_accounting_id'); $table->integer('plot_id')->nullable(); $table->integer('customer_id')->nullable();
        });
        Schema::create('journal_vouchers', function (Blueprint $table) {
            $table->id(); $table->string('voucher_number'); $table->string('type'); $table->string('reference')->nullable(); $table->integer('project_id'); $table->decimal('total_debit', 15, 2); $table->decimal('total_credit', 15, 2); $table->string('status'); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('journal_voucher_details', function (Blueprint $table) {
            $table->id(); $table->integer('journal_voucher_id'); $table->integer('account_id'); $table->decimal('debit', 15, 2); $table->decimal('credit', 15, 2); $table->string('description')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('customer_ledger', function (Blueprint $table) {
            $table->id(); $table->integer('customer_id'); $table->integer('project_id'); $table->integer('plot_id'); $table->string('transaction_type'); $table->decimal('amount_in', 15, 2); $table->decimal('amount_out', 15, 2); $table->timestamps();
        });
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id(); $table->string('type'); $table->integer('type_id'); $table->string('voucher_number')->nullable(); $table->integer('project_head_subheads_id'); $table->integer('customer_ledger_id')->nullable(); $table->string('reference')->nullable(); $table->decimal('amount_in', 15, 2); $table->decimal('amount_out', 15, 2); $table->string('detail')->nullable(); $table->timestamps();
        });
        Schema::create('booking_details', function (Blueprint $table) {
            $table->id(); $table->integer('booking_id'); $table->decimal('amount', 10, 2); $table->date('due_date');
        });
        Schema::create('booking_vouchers', function (Blueprint $table) {
            $table->id(); $table->integer('booking_id'); $table->string('voucher_series');
        });
    }
}
