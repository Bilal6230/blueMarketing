<?php

namespace Tests\Feature;

use App\Http\Controllers\BookingController;
use App\Http\Controllers\LedgerController;
use App\Models\BookingVoucher;
use App\Models\CustomerLedger;
use App\Models\DraftLedger;
use App\Models\Ledger;
use App\Models\Project;
use App\Models\ProjectHeadSubhead;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\TestCase;

class VoucherDeleteProtectionTest extends TestCase
{
    private User $user;
    private Project $project;
    private Project $otherProject;

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
        $this->actingAsDirectUpdateUser();
    }

    public function test_cr_posted_ledger_cannot_be_deleted(): void
    {
        $customerLedger = $this->createCustomerLedger($this->project->id, 'CR');
        $ledger = $this->createLedger($customerLedger->id, $this->project->id, 'CR');

        $response = $this->callLedgerDestroy($ledger->id);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'error_key' => 'voucher_posted_to_ledger',
            ]);

        $this->assertSame(1, (int) $ledger->fresh()->is_active);
        $this->assertSame(1, (int) $customerLedger->fresh()->is_active);
        $this->assertDatabaseCount('pending_updates', 0);
    }

    public function test_cp_posted_ledger_cannot_be_deleted(): void
    {
        $customerLedger = $this->createCustomerLedger($this->project->id, 'CP');
        $ledger = $this->createLedger($customerLedger->id, $this->project->id, 'CP');

        $response = $this->callLedgerDestroy($ledger->id);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'error_key' => 'voucher_posted_to_ledger',
            ]);

        $this->assertSame(1, (int) $ledger->fresh()->is_active);
        $this->assertSame(1, (int) $customerLedger->fresh()->is_active);
    }

    public function test_ppr_with_booking_voucher_ledger_id_cannot_be_deleted(): void
    {
        $customerLedger = $this->createCustomerLedger($this->project->id, 'PPR');
        $ledger = $this->createLedger($customerLedger->id, $this->project->id, 'PPR');
        $bookingVoucher = $this->createBookingVoucher($customerLedger->id, $this->project->id, $ledger->id);

        $response = $this->callBookingDestroy($customerLedger->id);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'error_key' => 'voucher_posted_to_ledger',
            ]);

        $this->assertSame(1, (int) $customerLedger->fresh()->is_active);
        $this->assertSame(1, (int) $bookingVoucher->fresh()->is_active);
    }

    public function test_ppr_with_matching_ppr_ledger_cannot_be_deleted_even_if_booking_voucher_ledger_id_is_null(): void
    {
        $customerLedger = $this->createCustomerLedger($this->project->id, 'PPR');
        $this->createLedger($customerLedger->id, $this->project->id, 'PPR');
        $bookingVoucher = $this->createBookingVoucher($customerLedger->id, $this->project->id, null);

        $response = $this->callBookingDestroy($customerLedger->id);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'error_key' => 'voucher_posted_to_ledger',
            ]);

        $this->assertSame(1, (int) $customerLedger->fresh()->is_active);
        $this->assertSame(1, (int) $bookingVoucher->fresh()->is_active);
    }

    public function test_ppr_with_no_ledger_can_be_soft_deleted(): void
    {
        $customerLedger = $this->createCustomerLedger($this->project->id, 'PPR');
        $bookingVoucher = $this->createBookingVoucher($customerLedger->id, $this->project->id, null);

        $response = $this->callBookingDestroy($customerLedger->id, ['delete_reason' => 'cleanup']);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertSame(0, (int) $customerLedger->fresh()->is_active);
        $this->assertSame('cleanup', $customerLedger->fresh()->delete_reason);
        $this->assertSame(0, (int) $bookingVoucher->fresh()->is_active);
        $this->assertSame('cleanup', $bookingVoucher->fresh()->delete_reason);
    }

    public function test_cross_project_delete_is_rejected_as_not_found(): void
    {
        $foreignCustomerLedger = $this->createCustomerLedger($this->otherProject->id, 'CR');
        $foreignLedger = $this->createLedger($foreignCustomerLedger->id, $this->otherProject->id, 'CR');
        $foreignPpr = $this->createCustomerLedger($this->otherProject->id, 'PPR');

        $ledgerResponse = $this->callLedgerDestroy($foreignLedger->id, [], $this->project->id);
        $bookingResponse = $this->callBookingDestroy($foreignPpr->id, [], $this->project->id);

        $ledgerResponse->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'error_key' => 'not_found',
            ]);

        $bookingResponse->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'error_key' => 'not_found',
            ]);
    }

    public function test_draft_ledger_delete_still_works(): void
    {
        $draftId = DB::table('draft_ledgers')->insertGetId([
            'detail' => 'Draft voucher',
            'name' => 'Draft voucher',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/admin/accounting/draft/ledger/destroy', 'DELETE', [
            'id' => $draftId,
        ]);
        $request->cookies->set('selected_action', (string) $this->project->id);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        $response = $this->app->make(LedgerController::class)->draftDestroy($request);

        TestResponse::fromBaseResponse($response)->assertStatus(302);
        $this->assertSame(0, (int) DraftLedger::findOrFail($draftId)->is_active);
    }

    private function createSchema(): void
    {
        Schema::dropAllTables();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken()->nullable();
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

        Schema::create('project_head_subheads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('head_accounting_id')->nullable();
            $table->unsignedBigInteger('subhead_accounting_id')->nullable();
            $table->unsignedBigInteger('plot_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
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
            $table->string('delete_reason')->nullable();
            $table->unsignedBigInteger('create_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->timestamps();
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
            $table->text('detail')->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
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

        $this->project = Project::create([
            'project' => 'Main Project',
            'address' => 'Address',
            'is_active' => 1,
            'create_by' => $this->user->id,
        ]);

        $this->otherProject = Project::create([
            'project' => 'Other Project',
            'address' => 'Address',
            'is_active' => 1,
            'create_by' => $this->user->id,
        ]);
    }

    private function actingAsDirectUpdateUser(): void
    {
        $mockUser = Mockery::mock($this->user)->makePartial();
        $mockUser->shouldReceive('hasRole')->andReturnFalse();
        $mockUser->shouldReceive('can')->with('direct-update')->andReturnTrue();
        Auth::setUser($mockUser);
    }

    private function createCustomerLedger(int $projectId, string $transactionType): CustomerLedger
    {
        return CustomerLedger::create([
            'customer_id' => 1,
            'project_id' => $projectId,
            'plot_id' => 1,
            'type_id' => 1,
            'reference' => $transactionType . '-REF-' . uniqid(),
            'payment_type' => $transactionType === 'PPR' ? 2 : 1,
            'passing_status' => $transactionType === 'PPR' ? 0 : null,
            'date' => '2026-06-12',
            'transaction_type' => $transactionType,
            'amount_in' => $transactionType === 'CR' ? 1000 : 0,
            'amount_out' => in_array($transactionType, ['CP', 'PPR'], true) ? 1000 : 0,
            'description' => $transactionType . ' voucher',
            'is_active' => 1,
            'is_approve' => 1,
        ]);
    }

    private function createLedger(int $customerLedgerId, int $projectId, string $type): Ledger
    {
        $projectHeadSubhead = ProjectHeadSubhead::create([
            'project_id' => $projectId,
            'head_accounting_id' => 10,
            'subhead_accounting_id' => 20,
            'plot_id' => 1,
            'customer_id' => 1,
        ]);

        return Ledger::create([
            'type' => $type,
            'type_id' => Ledger::count() + 1,
            'project_head_subheads_id' => $projectHeadSubhead->id,
            'customer_ledger_id' => $customerLedgerId,
            'reference' => $type . '-1',
            'amount_in' => $type === 'CR' || $type === 'PPR' ? 1000 : 0,
            'amount_out' => $type === 'CP' ? 1000 : 0,
            'detail' => $type . ' detail',
            'create_by' => $this->user->id,
            'update_by' => $this->user->id,
            'is_active' => 1,
            'status' => 0,
            'date' => '2026-06-12',
            'voucher_number' => Ledger::count() + 1,
        ]);
    }

    private function createBookingVoucher(int $customerLedgerId, int $projectId, ?int $ledgerId): BookingVoucher
    {
        return BookingVoucher::create([
            'booking_id' => 1,
            'customer_ledger_id' => $customerLedgerId,
            'ledger_id' => $ledgerId,
            'project_id' => $projectId,
            'customer_id' => 1,
            'plot_id' => 1,
            'voucher_series' => 'PPR',
            'voucher_number' => 11,
            'slip_reference' => 'PPR-11',
            'payment_type' => 2,
            'amount' => 1000,
            'receipt_date' => '2026-06-12',
            'description' => 'Received payment',
            'bank_id' => 1,
            't_number' => 'TXN-001',
            'passing_date' => '2026-06-13',
            'is_active' => 1,
            'is_approve' => 1,
            'create_by' => $this->user->id,
            'update_by' => $this->user->id,
        ]);
    }

    private function callLedgerDestroy(int $ledgerId, array $payload = [], ?int $selectedProjectId = null): TestResponse
    {
        $request = Request::create('/admin/accounting/ledger/destroy', 'DELETE', array_merge([
            'id' => $ledgerId,
            'delete_reason' => 'test delete',
        ], $payload));
        $request->cookies->set('selected_action', (string) ($selectedProjectId ?? $this->project->id));
        $request->headers->set('Accept', 'application/json');
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        $response = $this->app->make(LedgerController::class)->destroy($request);

        return TestResponse::fromBaseResponse($response);
    }

    private function callBookingDestroy(int $customerLedgerId, array $payload = [], ?int $selectedProjectId = null): TestResponse
    {
        $request = Request::create('/admin/booking/plot/destroy', 'DELETE', array_merge([
            'id' => $customerLedgerId,
            'delete_reason' => 'test delete',
        ], $payload));
        $request->cookies->set('selected_action', (string) ($selectedProjectId ?? $this->project->id));
        $request->headers->set('Accept', 'application/json');
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        $response = $this->app->make(BookingController::class)->destroy($request);

        return TestResponse::fromBaseResponse($response);
    }
}
