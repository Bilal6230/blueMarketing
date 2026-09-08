<?php

namespace Tests\Feature;

use App\Http\Controllers\BookingController;
use App\Models\Booking;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        DB::table('leads')->insert(['id' => 20, 'project_id' => 10, 'first_name' => 'Old', 'last_name' => 'Customer']);
        DB::table('plots')->insert(['id' => 30, 'project_id' => 10, 'name' => 'A-1', 'sold' => 1]);
        $this->booking = Booking::create($this->bookingValues());
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
        $this->assertSame(0, DB::table('project_head_subheads')->count());
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

    public function test_old_project_attachment_failure_rolls_back_earlier_file_transfer_mutation(): void
    {
        DB::table('project_head_subheads')->insert([
            'id' => 1, 'project_id' => 10, 'head_accounting_id' => 16,
            'subhead_accounting_id' => 100, 'plot_id' => 30, 'customer_id' => 20,
        ]);
        DB::statement("CREATE TRIGGER fail_old_sale_attachment BEFORE UPDATE ON project_head_subheads BEGIN SELECT RAISE(FAIL, 'forced attachment failure'); END");
        $payload = $this->transferPayload();
        $payload['total_price'] = '5100000.00';

        $this->callTransfer($payload);

        $current = $this->booking->fresh();
        $this->assertSame(0, bccomp((string) $current->total_price, '5000000.00', 2));
        $this->assertSame(20, (int) $current->customer_id);
        $this->assertSame(16, (int) DB::table('project_head_subheads')->value('head_accounting_id'));
        $this->assertSame(0, DB::table('head_accountings')->count());
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
            'booking_date' => '2026-09-08', 'status' => 1,
            'is_park' => 0, 'park_facing' => '0.00', 'is_corner' => 0,
            'carner_price' => '0.00', 'discount_value' => '0.00', 'broker_id' => null,
            'expected_updated_at' => $booking->updated_at?->format('Y-m-d H:i:s.u'),
            'expected_project_id' => 10, 'expected_customer_id' => 20,
            'expected_plot_id' => 30, 'expected_plot_type' => 1,
            'expected_plot_size' => '10', 'expected_plot_rate' => '500000.00',
            'expected_is_park' => 0, 'expected_park_facing' => '0.00',
            'expected_is_corner' => 0, 'expected_carner_price' => '0.00',
            'expected_dicount_value' => '0.00', 'expected_total_price' => '5000000.00',
            'expected_booking_date' => '2026-09-08', 'expected_status' => 'active',
            'expected_broker_id' => null,
        ];
    }

    private function bookingValues(): array
    {
        return [
            'project_id' => 10, 'customer_id' => 20, 'plot_id' => 30,
            'plot_type' => 1, 'plot_size' => '10', 'plot_rate' => 500000,
            'is_corner' => 0, 'is_park' => 0, 'park_facing' => 0,
            'carner_price' => 0, 'dicount_value' => 0, 'total_price' => 5000000,
            'broker_id' => null, 'booking_date' => '2026-09-08', 'status' => 'active',
            'user_id' => 1, 'cancel_status' => '0',
        ];
    }

    private function createSchema(): void
    {
        Schema::create('projects', fn (Blueprint $t) => $t->id());
        Schema::create('leads', function (Blueprint $t) {$t->id();$t->integer('project_id');$t->string('first_name');$t->string('last_name')->nullable();$t->string('nic_number')->nullable();$t->string('phone_number')->nullable();});
        Schema::create('plots', function (Blueprint $t) {$t->id();$t->integer('project_id');$t->string('name');$t->integer('sold');});
        Schema::create('bookings', function (Blueprint $t) {$t->id();$t->integer('project_id');$t->integer('customer_id');$t->integer('plot_id');$t->integer('plot_type');$t->string('plot_size');$t->decimal('plot_rate',10,2);$t->integer('is_corner');$t->integer('is_park');$t->decimal('park_facing',10,2);$t->decimal('carner_price',10,2);$t->decimal('dicount_value',10,2);$t->decimal('total_price',10,2);$t->integer('broker_id')->nullable();$t->dateTime('booking_date');$t->string('status');$t->integer('user_id');$t->string('cancel_status');$t->timestamps();$t->softDeletes();});
        Schema::create('booking_details', function (Blueprint $t) {$t->id();$t->integer('booking_id');$t->string('installment_details');$t->decimal('amount',10,2);$t->date('due_date');$t->timestamps();});
        Schema::create('head_accountings', function (Blueprint $t) {$t->id();$t->string('name');$t->integer('is_active');$t->integer('acct_type');$t->integer('create_by')->nullable();});
        Schema::create('project_head_subheads', function (Blueprint $t) {$t->id();$t->integer('project_id');$t->integer('head_accounting_id');$t->integer('subhead_accounting_id');$t->integer('plot_id');$t->integer('customer_id');});
    }
}
