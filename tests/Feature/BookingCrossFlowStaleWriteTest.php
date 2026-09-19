<?php

namespace Tests\Feature;

use App\Http\Controllers\BookingController;
use App\Models\Booking;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingCrossFlowStaleWriteTest extends TestCase
{
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
        DB::table('projects')->insert(['id' => 10]);
        DB::table('projects')->insert(['id' => 11]);
        DB::table('leads')->insert(['id' => 20, 'project_id' => 10, 'first_name' => 'Old', 'last_name' => 'Customer']);
        DB::table('leads')->insert(['id' => 21, 'project_id' => 10, 'first_name' => 'New', 'last_name' => 'Customer']);
        DB::table('plots')->insert(['id' => 30, 'project_id' => 10, 'name' => 'A-1', 'sold' => 1]);
        $this->booking = Booking::create($this->bookingValues());
        DB::table('head_accountings')->insert(['id' => 50, 'name' => 'Old Project Sales', 'is_active' => 1, 'acct_type' => 0]);
        DB::table('project_head_subheads')->insert([
            ['id' => 1, 'project_id' => 10, 'head_accounting_id' => 16, 'subhead_accounting_id' => 101, 'plot_id' => 30, 'customer_id' => 20],
            ['id' => 2, 'project_id' => 10, 'head_accounting_id' => 16, 'subhead_accounting_id' => 102, 'plot_id' => 30, 'customer_id' => 21],
            ['id' => 3, 'project_id' => 10, 'head_accounting_id' => 16, 'subhead_accounting_id' => 123, 'plot_id' => null, 'customer_id' => null],
        ]);
        Auth::shouldReceive('id')->andReturn(1);
    }

    public function test_same_timestamp_pricing_change_blocks_stale_file_transfer_before_mutation(): void
    {
        $payload = $this->transferPayload();
        $unchangedTimestamp = $this->booking->updated_at;
        DB::table('bookings')->where('id', $this->booking->id)->update([
            'plot_rate' => '480000.00',
            'total_price' => '4800000.00',
            'updated_at' => $unchangedTimestamp,
        ]);

        try {
            $this->callTransfer($payload);
            $this->fail('Expected stale File Transfer rejection.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('changed after you opened', $exception->getMessage());
        }

        $current = $this->booking->fresh();
        $this->assertSame(0, bccomp((string) $current->plot_rate, '480000.00', 2));
        $this->assertSame(0, bccomp((string) $current->total_price, '4800000.00', 2));
        $this->assertSame(20, (int) $current->customer_id);
        $this->assertSame(3, DB::table('project_head_subheads')->count());
    }

    public function test_stale_schedule_total_blocks_before_existing_rows_are_deleted(): void
    {
        DB::table('booking_details')->insert(['booking_id' => $this->booking->id, 'installment_details' => 'Existing', 'amount' => 100, 'due_date' => '2026-10-01']);
        DB::table('bookings')->where('id', $this->booking->id)->update(['total_price' => '4800000.00']);

        try {
            $this->callSchedule('5000000.00');
            $this->fail('Expected stale schedule rejection.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('booking amount changed', $exception->getMessage());
        }

        $this->assertSame(['Existing'], DB::table('booking_details')->pluck('installment_details')->all());
        $this->assertSame(0, bccomp((string) $this->booking->fresh()->total_price, '4800000.00', 2));
    }

    public function test_fresh_schedule_uses_locked_booking_total_and_replaces_schedule(): void
    {
        DB::table('booking_details')->insert(['booking_id' => $this->booking->id, 'installment_details' => 'Existing', 'amount' => 100, 'due_date' => '2026-10-01']);

        $this->callSchedule('5000000.00');

        $rows = DB::table('booking_details')->where('booking_id', $this->booking->id)->orderBy('id')->get();
        $this->assertCount(3, $rows);
        $this->assertNotContains('Existing', $rows->pluck('installment_details')->all());
        $this->assertSame(0, bccomp((string) $rows->sum('amount'), '5000000.00', 2));
    }

    public function test_valid_file_transfer_executes_voucher_path_and_preserves_booking_identity(): void
    {
        $payload = $this->trueTransferPayload();
        $this->assertNotSame((int) $payload['expected_customer_id'], (int) $payload['customer_id']);

        $this->callTransfer($payload);

        $current = $this->booking->fresh();
        $this->assertSame(21, (int) $current->customer_id);
        $this->assertSame(10, (int) $current->project_id);
        $this->assertSame(30, (int) $current->plot_id);
        $this->assertSame(1, (int) $current->plot_type);
        $this->assertSame('10', $current->plot_size);
        $this->assertSame(7, (int) $current->broker_id);
        $this->assertSame('active', $current->status);
        $this->assertSame('2026-09-08', (string) $current->booking_date);
        $this->assertSame('TRANSFER-' . $current->id, DB::table('journal_vouchers')->value('reference'));
        $this->assertGreaterThan(0, DB::table('journal_voucher_details')->count());
        $this->assertGreaterThan(0, DB::table('ledgers')->count());
        $this->assertGreaterThan(0, DB::table('customer_ledger')->count());
        $this->assertSame('2026-09-09', DB::table('journal_vouchers')->value('date'));
    }

    /** @dataProvider immutableTamperingProvider */
    public function test_file_transfer_rejects_requested_immutable_field_tampering(string $field, mixed $value): void
    {
        $payload = $this->trueTransferPayload();
        $payload[$field] = $value;

        try {
            $this->callTransfer($payload);
            $this->fail('Expected immutable File Transfer field rejection.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('cannot be changed during File Transfer', $exception->getMessage());
        }

        $this->assertSame(20, (int) $this->booking->fresh()->customer_id);
        $this->assertSame(0, DB::table('journal_vouchers')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
        $this->assertSame(0, DB::table('customer_ledger')->count());
        $this->assertSame(0, DB::table('booking_edit_audits')->count());
    }

    public function immutableTamperingProvider(): array
    {
        return [
            'project' => ['project_id', 11],
            'plot' => ['plot_id', '31'],
            'plot type' => ['plot_type', 2],
            'plot size' => ['plot_size', '11'],
            'broker' => ['broker_id', 8],
            'status' => ['status', 'inactive'],
        ];
    }

    public function test_true_transfer_late_attachment_failure_rolls_back_all_transfer_effects(): void
    {
        DB::statement("CREATE TRIGGER fail_old_sale_attachment BEFORE UPDATE ON project_head_subheads BEGIN SELECT RAISE(FAIL, 'forced attachment failure'); END");
        $payload = $this->trueTransferPayload();
        $payload['total_price'] = '5100000.00';
        $this->assertNotSame((int) $payload['expected_customer_id'], (int) $payload['customer_id']);

        $this->callTransfer($payload);

        $current = $this->booking->fresh();
        $this->assertSame(0, bccomp((string) $current->total_price, '5000000.00', 2));
        $this->assertSame(20, (int) $current->customer_id);
        $this->assertSame(16, (int) DB::table('project_head_subheads')->value('head_accounting_id'));
        $this->assertSame(0, DB::table('journal_vouchers')->count());
        $this->assertSame(0, DB::table('journal_voucher_details')->count());
        $this->assertSame(0, DB::table('ledgers')->count());
        $this->assertSame(0, DB::table('customer_ledger')->count());
        $this->assertSame(3, DB::table('project_head_subheads')->count());
    }

    private function callTransfer(array $payload): void
    {
        $request = Request::create('/admin/booking/plot/transfer', 'POST', $payload);
        $request->cookies->set('selected_action', '10');
        app()->instance('request', $request);
        app(BookingController::class)->update($request);
    }

    private function callSchedule(string $total): void
    {
        $request = Request::create('/admin/booking/plot/schedule/store', 'POST', [
            'booking_id' => $this->booking->id,
            'total_price' => $total,
            'instalment' => 2,
            'payment_plan' => 1,
            'start_date' => '2026-11-01',
            'installment_number' => ['Token'],
            'amount' => [1000000],
            'due_date' => ['2026-10-01'],
        ]);
        $request->cookies->set('selected_action', '10');
        app()->instance('request', $request);
        app(BookingController::class)->storePaymentSchedule($request);
    }

    private function transferPayload(): array
    {
        $booking = $this->booking;
        return [
            'id' => $booking->id, 'project_id' => 10, 'customer_id' => 20,
            'plot_id' => '30', 'plot_type' => 1, 'plot_size' => '10',
            'plot_rate' => '500000.00', 'total_price' => '5000000.00',
            'booking_date' => '2026-09-09', 'status' => 'active',
            'is_park' => 0, 'park_facing' => '0.00', 'is_corner' => 0,
            'carner_price' => '0.00', 'discount_value' => '0.00', 'broker_id' => 7,
            'expected_updated_at' => $booking->updated_at?->format('Y-m-d H:i:s.u'),
            'expected_project_id' => 10, 'expected_customer_id' => 20,
            'expected_plot_id' => 30, 'expected_plot_type' => 1,
            'expected_plot_size' => '10', 'expected_plot_rate' => '500000.00',
            'expected_is_park' => 0, 'expected_park_facing' => '0.00',
            'expected_is_corner' => 0, 'expected_carner_price' => '0.00',
            'expected_dicount_value' => '0.00', 'expected_total_price' => '5000000.00',
            'expected_booking_date' => '2026-09-08', 'expected_status' => 'active',
            'expected_broker_id' => 7,
        ];
    }

    private function trueTransferPayload(): array
    {
        return array_merge($this->transferPayload(), ['customer_id' => 21]);
    }

    private function bookingValues(): array
    {
        return [
            'project_id' => 10, 'customer_id' => 20, 'plot_id' => 30,
            'plot_type' => 1, 'plot_size' => '10', 'plot_rate' => 500000,
            'is_corner' => 0, 'is_park' => 0, 'park_facing' => 0,
            'carner_price' => 0, 'dicount_value' => 0, 'total_price' => 5000000,
            'broker_id' => 7, 'booking_date' => '2026-09-08', 'status' => 'active',
            'user_id' => 1, 'cancel_status' => '0',
        ];
    }

    private function createSchema(): void
    {
        Schema::create('projects', fn (Blueprint $t) => $t->id());
        Schema::create('leads', function (Blueprint $t) {$t->id();$t->integer('project_id');$t->string('first_name');$t->string('last_name')->nullable();$t->string('nic_number')->nullable();$t->string('phone_number')->nullable();});
        Schema::create('plots', function (Blueprint $t) {$t->id();$t->integer('project_id');$t->string('name');$t->integer('sold');$t->timestamps();});
        Schema::create('bookings', function (Blueprint $t) {$t->id();$t->integer('project_id');$t->integer('customer_id');$t->integer('plot_id');$t->integer('plot_type');$t->string('plot_size');$t->decimal('plot_rate',10,2);$t->integer('is_corner');$t->integer('is_park');$t->decimal('park_facing',10,2);$t->decimal('carner_price',10,2);$t->decimal('dicount_value',10,2);$t->decimal('total_price',10,2);$t->integer('broker_id')->nullable();$t->dateTime('booking_date');$t->string('status');$t->integer('user_id');$t->string('cancel_status');$t->timestamps();$t->softDeletes();});
        Schema::create('booking_details', function (Blueprint $t) {$t->id();$t->integer('booking_id');$t->string('installment_details');$t->decimal('amount',10,2);$t->date('due_date');$t->timestamps();});
        Schema::create('head_accountings', function (Blueprint $t) {$t->id();$t->string('name');$t->integer('is_active');$t->integer('acct_type');$t->integer('create_by')->nullable();$t->timestamps();});
        Schema::create('project_head_subheads', function (Blueprint $t) {$t->id();$t->integer('project_id');$t->integer('head_accounting_id');$t->integer('subhead_accounting_id');$t->integer('plot_id')->nullable();$t->integer('customer_id')->nullable();$t->timestamps();});
        Schema::create('journal_vouchers', function (Blueprint $t) {$t->id();$t->integer('voucher_number');$t->string('type');$t->string('reference');$t->date('date');$t->text('description');$t->decimal('total_debit',10,2);$t->decimal('total_credit',10,2);$t->integer('project_id');$t->integer('created_by')->nullable();$t->string('status');$t->timestamps();$t->softDeletes();});
        Schema::create('journal_voucher_details', function (Blueprint $t) {$t->id();$t->integer('journal_voucher_id');$t->integer('account_id');$t->decimal('debit',10,2);$t->decimal('credit',10,2);$t->text('description');$t->timestamps();$t->softDeletes();});
        Schema::create('customer_ledger', function (Blueprint $t) {$t->id();$t->integer('customer_id');$t->integer('project_id');$t->integer('plot_id');$t->integer('type_id')->nullable();$t->date('date');$t->string('transaction_type');$t->decimal('amount_in',10,2);$t->decimal('amount_out',10,2);$t->text('description');$t->integer('is_active');$t->integer('is_approve');$t->timestamps();});
        Schema::create('ledgers', function (Blueprint $t) {$t->id();$t->string('type');$t->integer('type_id');$t->integer('voucher_number');$t->integer('project_head_subheads_id');$t->integer('customer_ledger_id')->nullable();$t->integer('reference');$t->decimal('amount_in',10,2);$t->decimal('amount_out',10,2);$t->text('detail');$t->date('date');$t->integer('is_active')->default(1);$t->integer('create_by')->nullable();$t->integer('update_by')->nullable();$t->integer('status')->default(0);$t->timestamps();});
        Schema::create('booking_edit_audits', function (Blueprint $t) {$t->id();});
    }
}
