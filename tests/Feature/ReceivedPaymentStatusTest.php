<?php

namespace Tests\Feature;

use App\Http\Controllers\BookingController;
use App\Http\Controllers\LedgerController;
use App\Models\Booking;
use App\Models\BookingVoucher;
use App\Models\CustomerLedger;
use App\Models\Lead;
use App\Models\Ledger;
use App\Models\Plot;
use App\Models\Project;
use App\Models\ProjectHeadSubhead;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReceivedPaymentStatusTest extends TestCase
{
    private User $user;
    private Project $project;
    private Lead $customer;
    private Plot $plot;
    private ProjectHeadSubhead $bookingReceiptAccount;
    private ProjectHeadSubhead $cashVoucherAccount;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->withoutMiddleware();
        $this->createSchema();
        $this->seedBaseData();
        $this->actingAs($this->user);
    }

    public function test_received_payment_status_labels_follow_passing_status_rules(): void
    {
        $cash = $this->createReceivedPayment(1, null, 1000, 'CASH-001');
        $pending = $this->createReceivedPayment(2, 0, 1100, 'PENDING-001');
        $passed = $this->createReceivedPayment(2, 1, 1200, 'PASSED-001');
        $returned = $this->createReceivedPayment(2, 2, 1300, 'RETURNED-001');
        $bounced = $this->createReceivedPayment(3, 3, 1400, 'BOUNCED-001');

        $rows = $this->receivedPaymentRows()->keyBy('customer_ledger_id');

        $this->assertSame('Posted', $rows[$cash->id]->payment_status_label);
        $this->assertSame('Pending', $rows[$pending->id]->payment_status_label);
        $this->assertSame('Passed', $rows[$passed->id]->payment_status_label);
        $this->assertSame('Returned', $rows[$returned->id]->payment_status_label);
        $this->assertSame('Bounced', $rows[$bounced->id]->payment_status_label);
    }

    public function test_received_payment_modal_uses_single_status_label(): void
    {
        $pending = $this->createReceivedPayment(2, 0, 1500, 'MODAL-001');

        $response = $this->callFetchVoucher($pending->id);

        $response->assertOk()
            ->assertJsonPath('data.status_label', 'Pending')
            ->assertJsonMissingPath('data.approval_status_label')
            ->assertJsonMissingPath('data.clearance_status_label');
    }

    public function test_cash_out_pass_updates_received_payment_status_to_passed(): void
    {
        $pending = $this->createReceivedPayment(2, 0, 1800, 'SYNC-001');

        $response = $this->callLedgerStore([
            'amount' => '1800',
            'detail' => 'Pass pending receipt',
            'accounts_id' => 10,
            'subaccounts_id' => 20,
            'payment_type' => 2,
            'date' => '2026-06-12',
            'voucher' => 'CP-1',
            'voucher_number' => 'CP-1',
            'reference' => 'CP-REF-001',
            't_number' => 'CLR-1800',
            'bank_id' => 1,
            'selected_pending_payment_id' => $pending->id,
            'pending_status' => 1,
            'passing_date' => '2026-06-13',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'pending_status' => 1,
            ]);

        $pending->refresh();
        $this->assertSame(1, (int) $pending->passing_status);

        $rows = $this->receivedPaymentRows()->keyBy('customer_ledger_id');
        $this->assertSame('Passed', $rows[$pending->id]->payment_status_label);

        $voucherResponse = $this->callFetchVoucher($pending->id);
        $voucherResponse->assertOk()
            ->assertJsonPath('data.status_label', 'Passed');
    }

    private function receivedPaymentRows(): Collection
    {
        $request = Request::create('/admin/booking/plot/voucher', 'GET');
        $request->cookies->set('selected_action', (string) $this->project->id);
        $this->app->instance('request', $request);

        $view = $this->app->make(BookingController::class)->cash_in($request);
        $data = $view->getData();

        return collect($data['data']);
    }

    private function callFetchVoucher(int $id): TestResponse
    {
        $request = Request::create('/admin/booking/plot/show', 'POST', [
            'id' => $id,
        ]);
        $request->cookies->set('selected_action', (string) $this->project->id);
        $request->headers->set('Accept', 'application/json');
        $this->app->instance('request', $request);

        $response = $this->app->make(BookingController::class)->fatch_voucher($request);

        return TestResponse::fromBaseResponse($response);
    }

    private function callLedgerStore(array $payload): TestResponse
    {
        $request = Request::create('/admin/accounting/ledger/store', 'POST', $payload);
        $request->cookies->set('selected_action', (string) $this->project->id);
        $request->headers->set('Accept', 'application/json');
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        $response = $this->app->make(LedgerController::class)->store($request);

        return TestResponse::fromBaseResponse($response);
    }

    private function createReceivedPayment(int $paymentType, ?int $passingStatus, float $amount, string $reference): CustomerLedger
    {
        $customerLedger = CustomerLedger::create([
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
            'plot_id' => $this->plot->id,
            'type_id' => CustomerLedger::count() + 1,
            'reference' => $reference,
            'payment_type' => $paymentType,
            't_number' => $paymentType === 1 ? null : 'TXN-' . CustomerLedger::count(),
            'bank_id' => $paymentType === 1 ? null : 1,
            'passing_date' => $paymentType === 1 ? null : '2026-06-13',
            'passing_status' => $passingStatus,
            'date' => '2026-06-12',
            'transaction_type' => 'PPR',
            'amount_in' => 0,
            'amount_out' => $amount,
            'description' => 'Received payment',
            'is_active' => 1,
            'is_approve' => 0,
        ]);

        $ledger = Ledger::create([
            'customer_ledger_id' => $customerLedger->id,
            'voucher_number' => Ledger::count() + 1,
            'type' => 'PPR',
            'type_id' => Ledger::count() + 1,
            'project_head_subheads_id' => $this->bookingReceiptAccount->id,
            'reference' => $reference,
            'amount_in' => $amount,
            'amount_out' => 0,
            'is_active' => 1,
            'date' => '2026-06-12',
            'detail' => 'PPR detail',
            'update_by' => $this->user->id,
            'create_by' => $this->user->id,
            'status' => 0,
        ]);

        BookingVoucher::create([
            'booking_id' => 1,
            'customer_ledger_id' => $customerLedger->id,
            'ledger_id' => $ledger->id,
            'project_id' => $this->project->id,
            'customer_id' => $this->customer->id,
            'plot_id' => $this->plot->id,
            'voucher_series' => 'PPR',
            'voucher_number' => $ledger->voucher_number,
            'slip_reference' => $reference,
            'payment_type' => $paymentType,
            'amount' => $amount,
            'receipt_date' => '2026-06-12',
            'description' => 'Received payment',
            'bank_id' => $paymentType === 1 ? null : 1,
            't_number' => $paymentType === 1 ? null : 'TXN-' . $customerLedger->id,
            'passing_date' => $paymentType === 1 ? null : '2026-06-13',
            'is_active' => 1,
            'is_approve' => 0,
            'create_by' => $this->user->id,
            'update_by' => $this->user->id,
        ]);

        return $customerLedger->fresh();
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken()->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project');
            $table->string('address')->nullable();
            $table->integer('is_active')->default(1);
            $table->integer('create_by')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('nic_number')->nullable();
            $table->string('home_address')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->integer('is_active')->default(1);
            $table->integer('create_by')->nullable();
            $table->timestamps();
        });

        Schema::create('plots', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('1');
            $table->string('size')->default('5');
            $table->string('unit')->default('marla');
            $table->text('description')->nullable();
            $table->integer('project_id');
            $table->integer('road_id')->default(1);
            $table->integer('facing_id')->default(1);
            $table->integer('is_active')->default(1);
            $table->integer('create_by')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('head_accountings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('acct_type')->nullable();
            $table->integer('is_active')->default(1);
            $table->integer('create_by')->nullable();
            $table->timestamps();
        });

        Schema::create('subhead_accountings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('is_active')->default(1);
            $table->integer('create_by')->nullable();
            $table->timestamps();
        });

        Schema::create('project_head_subheads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('head_accounting_id')->nullable();
            $table->unsignedBigInteger('subhead_accounting_id')->nullable();
            $table->unsignedBigInteger('plot_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->integer('project_id');
            $table->integer('customer_id');
            $table->integer('plot_id');
            $table->tinyInteger('plot_type')->default(0);
            $table->string('plot_size')->default('5 marla');
            $table->decimal('plot_rate', 10, 2)->default(0);
            $table->integer('is_corner')->default(0);
            $table->integer('is_park')->default(0);
            $table->decimal('park_facing', 10, 2)->default(0);
            $table->decimal('carner_price', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->integer('broker_id')->default(1);
            $table->timestamp('booking_date')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('user_id');
            $table->enum('cancel_status', ['0', '1'])->default('0');
            $table->longText('reason')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('customer_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('plot_id')->nullable();
            $table->unsignedBigInteger('type_id')->nullable();
            $table->string('reference')->nullable();
            $table->unsignedTinyInteger('payment_type')->nullable();
            $table->string('t_number')->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->date('passing_date')->nullable();
            $table->unsignedTinyInteger('passing_status')->nullable();
            $table->json('check_history')->nullable();
            $table->date('date')->nullable();
            $table->string('transaction_type')->nullable();
            $table->decimal('amount_in', 12, 2)->default(0);
            $table->decimal('amount_out', 12, 2)->default(0);
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_approve')->default(false);
            $table->text('note')->nullable();
            $table->date('bank_post_at')->nullable();
            $table->string('delete_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->integer('type_id')->nullable();
            $table->unsignedBigInteger('project_head_subheads_id')->nullable();
            $table->unsignedBigInteger('customer_ledger_id')->nullable();
            $table->string('reference')->nullable();
            $table->decimal('amount_in', 12, 2)->default(0);
            $table->decimal('amount_out', 12, 2)->default(0);
            $table->text('detail')->nullable();
            $table->integer('create_by')->nullable();
            $table->integer('update_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->nullable();
            $table->date('date')->nullable();
            $table->unsignedInteger('voucher_number')->nullable();
            $table->string('delete_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('booking_vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('customer_ledger_id')->nullable()->unique();
            $table->unsignedBigInteger('ledger_id')->nullable()->unique();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('plot_id')->nullable();
            $table->string('voucher_series', 20)->nullable();
            $table->unsignedInteger('voucher_number')->nullable();
            $table->string('slip_reference')->nullable();
            $table->unsignedTinyInteger('payment_type')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('receipt_date')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->string('t_number')->nullable();
            $table->date('passing_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_approve')->default(false);
            $table->unsignedBigInteger('create_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pending_updates', function (Blueprint $table) {
            $table->id();
            $table->string('table_name');
            $table->unsignedBigInteger('record_id');
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
        });

        Schema::create('draft_ledgers', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    private function seedBaseData(): void
    {
        $this->user = User::create([
            'name' => 'Tester',
            'email' => 'tester@example.com',
            'password' => Hash::make('password'),
        ]);
        $this->user->setRelation('roles', collect([(object) ['name' => 'Tester']]));

        DB::table('roles')->insert([
            'name' => 'Tester',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->project = Project::create([
            'project' => 'Main Project',
            'address' => 'Address',
            'is_active' => 1,
            'create_by' => $this->user->id,
        ]);

        $this->customer = Lead::create([
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'phone_number' => '03000000000',
            'mobile_number' => '03000000000',
            'nic_number' => '12345-1234567-1',
            'home_address' => 'Address',
            'project_id' => $this->project->id,
            'is_active' => 1,
            'create_by' => $this->user->id,
        ]);

        $this->plot = Plot::create([
            'name' => '101',
            'type' => '1',
            'size' => '5',
            'unit' => 'marla',
            'description' => 'Plot',
            'project_id' => $this->project->id,
            'road_id' => 1,
            'facing_id' => 1,
            'is_active' => 1,
            'create_by' => $this->user->id,
            'amount' => 0,
        ]);

        DB::table('head_accountings')->insert([
            ['id' => 16, 'name' => 'Booking Receipt', 'acct_type' => 1, 'is_active' => 1, 'create_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 10, 'name' => 'Cash Voucher', 'acct_type' => 1, 'is_active' => 1, 'create_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('subhead_accountings')->insert([
            'id' => 20,
            'name' => 'Customer Account',
            'is_active' => 1,
            'create_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->bookingReceiptAccount = ProjectHeadSubhead::create([
            'project_id' => $this->project->id,
            'head_accounting_id' => 16,
            'subhead_accounting_id' => 20,
            'plot_id' => $this->plot->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->cashVoucherAccount = ProjectHeadSubhead::create([
            'project_id' => $this->project->id,
            'head_accounting_id' => 10,
            'subhead_accounting_id' => 20,
            'plot_id' => $this->plot->id,
            'customer_id' => $this->customer->id,
        ]);

        Booking::create([
            'project_id' => $this->project->id,
            'customer_id' => $this->customer->id,
            'plot_id' => $this->plot->id,
            'plot_type' => 1,
            'plot_size' => '5 marla',
            'plot_rate' => 1000,
            'is_corner' => 0,
            'is_park' => 0,
            'park_facing' => 0,
            'carner_price' => 0,
            'total_price' => 500000,
            'broker_id' => 1,
            'booking_date' => now(),
            'status' => 'active',
            'user_id' => $this->user->id,
            'cancel_status' => '0',
        ]);
    }
}
