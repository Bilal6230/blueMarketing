<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CustomerLedger;
use App\Models\JournalVoucher;
use App\Models\JournalVoucherDetail;
use App\Models\Ledger;
use App\Services\BookingSalesVoucherSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingSalesVoucherSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_new_discounted_booking_uses_final_total_everywhere(): void
    {
        $fixture = $this->fixture('10927500.00');

        app(BookingSalesVoucherSyncService::class)->sync($fixture['booking']);

        $this->assertAccountingAmount($fixture, '10927500.00');
    }

    public function test_existing_voucher_is_corrected_after_booking_amount_changes(): void
    {
        $fixture = $this->fixture('11625000.00');
        $fixture['booking']->update(['total_price' => '10927500.00']);

        app(BookingSalesVoucherSyncService::class)->sync($fixture['booking']);

        $this->assertAccountingAmount($fixture, '10927500.00');
    }

    public function test_sync_is_idempotent_and_does_not_duplicate_rows(): void
    {
        $fixture = $this->fixture('11625000.00');
        $fixture['booking']->update(['total_price' => '10927500.00']);
        $service = app(BookingSalesVoucherSyncService::class);

        $service->sync($fixture['booking']);
        $counts = $this->counts($fixture);
        $service->sync($fixture['booking']);

        $this->assertSame($counts, $this->counts($fixture));
        $this->assertAccountingAmount($fixture, '10927500.00');
    }

    public function test_duplicate_booking_sales_voucher_is_rejected_without_changes(): void
    {
        $fixture = $this->fixture('11625000.00');
        DB::table('journal_vouchers')->insert([
            'voucher_number' => 'SV-duplicate', 'type' => 'SV', 'reference' => 'BOOKING-' . $fixture['booking']->id,
            'date' => '2026-01-01', 'total_debit' => 1, 'total_credit' => 1,
            'project_id' => $fixture['booking']->project_id, 'created_by' => 1, 'status' => 'pending',
        ]);

        $this->expectException(\RuntimeException::class);
        app(BookingSalesVoucherSyncService::class)->sync($fixture['booking']);
    }

    public function test_voucher_number_is_preserved(): void
    {
        $fixture = $this->fixture('11625000.00');
        $fixture['booking']->update(['total_price' => '10927500.00']);

        app(BookingSalesVoucherSyncService::class)->sync($fixture['booking']);

        $this->assertSame('SV-77', (string) $fixture['voucher']->fresh()->voucher_number);
    }

    public function test_voucher_remains_balanced(): void
    {
        $fixture = $this->fixture('11625000.00');
        $fixture['booking']->update(['total_price' => '10927500.00']);

        app(BookingSalesVoucherSyncService::class)->sync($fixture['booking']);

        $voucher = $fixture['voucher']->fresh();
        $this->assertSame(0, bccomp((string) $voucher->total_debit, (string) $voucher->total_credit, 2));
    }

    public function test_sync_is_isolated_to_the_bookings_project(): void
    {
        $a = $this->fixture('11625000.00', 1, 1);
        $b = $this->fixture('2887500.00', 2, 2);
        $a['booking']->update(['total_price' => '10927500.00']);

        app(BookingSalesVoucherSyncService::class)->sync($a['booking']);

        $this->assertAccountingAmount($a, '10927500.00');
        $this->assertAccountingAmount($b, '2887500.00');
    }

    public function test_repair_command_dry_run_reports_without_writing(): void
    {
        $fixture = $this->fixture('11625000.00');
        $fixture['booking']->update(['total_price' => '10927500.00']);

        $exit = Artisan::call('accounting:sync-booking-sales-voucher', ['--booking' => $fixture['booking']->id, '--dry-run' => true]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Dry run: no records were changed.', Artisan::output());
        $this->assertAccountingAmount($fixture, '11625000.00', false);
    }

    public function test_repair_command_executes_without_duplicates_and_preserves_number(): void
    {
        $fixture = $this->fixture('11625000.00');
        $fixture['booking']->update(['total_price' => '10927500.00']);
        $counts = $this->counts($fixture);

        $exit = Artisan::call('accounting:sync-booking-sales-voucher', ['--booking' => $fixture['booking']->id]);

        $this->assertSame(0, $exit);
        $this->assertSame($counts, $this->counts($fixture));
        $this->assertSame('SV-77', (string) $fixture['voucher']->fresh()->voucher_number);
        $this->assertAccountingAmount($fixture, '10927500.00');
    }

    private function fixture(string $accountingAmount, int $projectId = 1, int $bookingId = 1): array
    {
        DB::table('projects')->insert(['id' => $projectId, 'name' => "Project {$projectId}"]);
        DB::table('bookings')->insert([
            'id' => $bookingId, 'project_id' => $projectId, 'customer_id' => $bookingId,
            'plot_id' => $bookingId, 'total_price' => $accountingAmount, 'booking_date' => '2026-01-01',
        ]);
        $customerAccount = DB::table('project_head_subheads')->insertGetId([
            'project_id' => $projectId, 'head_accounting_id' => 16, 'subhead_accounting_id' => 1000 + $bookingId,
            'plot_id' => $bookingId, 'customer_id' => $bookingId,
        ]);
        $saleAccount = DB::table('project_head_subheads')->insertGetId([
            'project_id' => $projectId, 'head_accounting_id' => 16, 'subhead_accounting_id' => 123,
        ]);
        $customerLedgerId = DB::table('customer_ledger')->insertGetId([
            'date' => '2026-01-01', 'customer_id' => $bookingId, 'project_id' => $projectId, 'plot_id' => $bookingId,
            'transaction_type' => 'Bo', 'amount_in' => $accountingAmount, 'amount_out' => 0,
        ]);
        $voucherId = DB::table('journal_vouchers')->insertGetId([
            'voucher_number' => 'SV-77', 'type' => 'SV', 'reference' => 'BOOKING-' . $bookingId,
            'date' => '2026-01-01', 'total_debit' => $accountingAmount, 'total_credit' => $accountingAmount,
            'project_id' => $projectId, 'created_by' => 1, 'status' => 'pending',
        ]);
        $customerLedgerRow = DB::table('ledgers')->insertGetId([
            'type' => 'SV', 'type_id' => $voucherId, 'voucher_number' => 'SV-77', 'project_head_subheads_id' => $customerAccount,
            'customer_ledger_id' => $customerLedgerId, 'reference' => (string) $bookingId, 'amount_in' => 0,
            'amount_out' => $accountingAmount, 'detail' => 'Booking customer', 'date' => '2026-01-01',
        ]);
        $saleLedgerRow = DB::table('ledgers')->insertGetId([
            'type' => 'SV', 'type_id' => $voucherId, 'voucher_number' => 'SV-77', 'project_head_subheads_id' => $saleAccount,
            'customer_ledger_id' => $customerLedgerId, 'reference' => (string) $bookingId, 'amount_in' => $accountingAmount,
            'amount_out' => 0, 'detail' => 'Total Sale', 'date' => '2026-01-01',
        ]);
        $customerDetail = DB::table('journal_voucher_details')->insertGetId([
            'journal_voucher_id' => $voucherId, 'account_id' => $customerAccount, 'debit' => 0,
            'credit' => $accountingAmount, 'description' => 'Booking customer',
        ]);
        $saleDetail = DB::table('journal_voucher_details')->insertGetId([
            'journal_voucher_id' => $voucherId, 'account_id' => $saleAccount, 'debit' => $accountingAmount,
            'credit' => 0, 'description' => 'Total Sale',
        ]);

        return [
            'booking' => Booking::findOrFail($bookingId), 'voucher' => JournalVoucher::findOrFail($voucherId),
            'customer_ledger' => CustomerLedger::findOrFail($customerLedgerId),
            'customer_ledger_row' => Ledger::findOrFail($customerLedgerRow), 'sale_ledger_row' => Ledger::findOrFail($saleLedgerRow),
            'customer_detail' => JournalVoucherDetail::findOrFail($customerDetail), 'sale_detail' => JournalVoucherDetail::findOrFail($saleDetail),
        ];
    }

    private function assertAccountingAmount(array $fixture, string $amount, bool $assertBooking = true): void
    {
        if ($assertBooking) {
            $this->assertSame($amount, bcadd((string) $fixture['booking']->fresh()->total_price, '0', 2));
        }
        $this->assertSame($amount, bcadd((string) $fixture['voucher']->fresh()->total_debit, '0', 2));
        $this->assertSame($amount, bcadd((string) $fixture['voucher']->fresh()->total_credit, '0', 2));
        $this->assertSame($amount, bcadd((string) $fixture['customer_ledger']->fresh()->amount_in, '0', 2));
        $this->assertSame($amount, bcadd((string) $fixture['customer_ledger_row']->fresh()->amount_out, '0', 2));
        $this->assertSame($amount, bcadd((string) $fixture['sale_ledger_row']->fresh()->amount_in, '0', 2));
        $this->assertSame($amount, bcadd((string) $fixture['customer_detail']->fresh()->credit, '0', 2));
        $this->assertSame($amount, bcadd((string) $fixture['sale_detail']->fresh()->debit, '0', 2));
    }

    private function counts(array $fixture): array
    {
        return [
            JournalVoucher::where('reference', 'BOOKING-' . $fixture['booking']->id)->count(),
            Ledger::where('type_id', $fixture['voucher']->id)->count(),
            JournalVoucherDetail::where('journal_voucher_id', $fixture['voucher']->id)->count(),
        ];
    }

    private function createSchema(): void
    {
        Schema::create('projects', function (Blueprint $table) { $table->id(); $table->string('name'); });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('project_id'); $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('plot_id'); $table->decimal('total_price', 15, 2); $table->date('booking_date');
            $table->timestamps(); $table->softDeletes();
        });
        Schema::create('project_head_subheads', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('project_id'); $table->unsignedBigInteger('head_accounting_id');
            $table->unsignedBigInteger('subhead_accounting_id'); $table->unsignedBigInteger('plot_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable(); $table->timestamps();
        });
        Schema::create('customer_ledger', function (Blueprint $table) {
            $table->id(); $table->date('date'); $table->unsignedBigInteger('customer_id'); $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('plot_id')->nullable(); $table->string('transaction_type');
            $table->decimal('amount_in', 15, 2); $table->decimal('amount_out', 15, 2); $table->timestamps();
        });
        Schema::create('journal_vouchers', function (Blueprint $table) {
            $table->id(); $table->string('voucher_number'); $table->string('type'); $table->string('reference');
            $table->date('date'); $table->decimal('total_debit', 15, 2); $table->decimal('total_credit', 15, 2);
            $table->unsignedBigInteger('project_id'); $table->unsignedBigInteger('created_by'); $table->string('status');
            $table->timestamps(); $table->softDeletes();
        });
        Schema::create('journal_voucher_details', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('journal_voucher_id'); $table->unsignedBigInteger('account_id');
            $table->decimal('debit', 15, 2); $table->decimal('credit', 15, 2); $table->text('description')->nullable();
            $table->timestamps(); $table->softDeletes();
        });
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id(); $table->string('type'); $table->unsignedBigInteger('type_id'); $table->string('voucher_number');
            $table->unsignedBigInteger('project_head_subheads_id'); $table->unsignedBigInteger('customer_ledger_id')->nullable();
            $table->string('reference'); $table->decimal('amount_in', 15, 2); $table->decimal('amount_out', 15, 2);
            $table->text('detail')->nullable(); $table->date('date'); $table->timestamps();
        });
    }
}
