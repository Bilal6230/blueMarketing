<?php

namespace Tests\Feature;

use App\Http\Controllers\Finance\VoucherController;
use App\Http\Controllers\LeadController;
use App\Models\Lead;
use App\Models\PendingUpdate;
use App\Models\Project;
use App\Models\User;
use App\Models\Work;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class LeadPendingApprovalTest extends TestCase
{
    private User $admin;
    private User $ownerA;
    private User $ownerB;
    private User $ownerC;
    private User $ownerD;
    private Project $project;

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
    }

    public function test_lead_approval_page_filters_to_lead_requests_only(): void
    {
        $lead = $this->createLead('Customer One');
        PendingUpdate::create([
            'table_name' => 'leads',
            'record_id' => $lead->id,
            'old_values' => [],
            'new_values' => [],
            'status' => 'pending',
            'submitted_by' => $this->ownerA->id,
        ]);

        PendingUpdate::create([
            'table_name' => 'ledgers',
            'record_id' => 10,
            'old_values' => [],
            'new_values' => [],
            'status' => 'pending',
            'submitted_by' => $this->ownerB->id,
        ]);

        PendingUpdate::create([
            'table_name' => 'customer_ledger',
            'record_id' => 20,
            'old_values' => [],
            'new_values' => [],
            'status' => 'pending',
            'submitted_by' => $this->ownerC->id,
        ]);

        $view = $this->callLeadApprovalIndex($this->admin, true);
        $pendingUpdates = $view->getData()['pendingUpdates'];

        $this->assertCount(1, $pendingUpdates);
        $this->assertSame('leads', $pendingUpdates->first()->table_name);
    }

    public function test_lead_page_keeps_actual_comment_authors_and_all_current_assigned_users(): void
    {
        $lead = $this->createLead('Customer Two');
        $this->assignLead($lead->id, $this->ownerA->id);
        $this->assignLead($lead->id, $this->ownerB->id);

        Work::create([
            'lead_id' => $lead->id,
            'comment' => 'Visit the project',
            'user_id' => $this->ownerA->id,
            'created_at' => '2026-08-10 10:30:00',
            'updated_at' => '2026-08-10 10:30:00',
        ]);

        Work::create([
            'lead_id' => $lead->id,
            'comment' => 'Sale Done',
            'user_id' => $this->ownerB->id,
            'created_at' => '2026-08-11 12:15:00',
            'updated_at' => '2026-08-11 12:15:00',
        ]);

        PendingUpdate::create([
            'table_name' => 'leads',
            'record_id' => $lead->id,
            'old_values' => [],
            'new_values' => [],
            'status' => 'pending',
            'submitted_by' => $this->ownerC->id,
        ]);

        $view = $this->callLeadApprovalIndex($this->admin, true);
        $pending = $view->getData()['pendingUpdates']->first();

        $this->assertSame('Owner A, Owner B', $pending->current_assigned_users_label);
        $this->assertSame(['Owner A', 'Owner B'], $pending->current_assigned_users);
        $this->assertSame($this->ownerC->name, $pending->requested_user_name);
        $this->assertSame($this->ownerB->name, $pending->comments_for_modal[0]['user_name']);
        $this->assertSame($this->ownerA->name, $pending->comments_for_modal[1]['user_name']);
        $this->assertNotSame($this->ownerC->name, $pending->comments_for_modal[0]['user_name']);
        $this->assertNotSame($this->ownerC->name, $pending->comments_for_modal[1]['user_name']);
    }

    public function test_request_edit_button_ignores_tampered_table_name(): void
    {
        $lead = $this->createLead('Customer Three');
        Auth::setUser($this->admin);

        $request = Request::create('/admin/crm/request-edit', 'POST', [
            'table_name' => 'ledgers',
            'record_id' => $lead->id,
        ]);

        $response = app(LeadController::class)->requestEditBtn($request);

        $payload = $response->getData(true);
        $this->assertSame('success', $payload['status']);
        $this->assertDatabaseHas('pending_updates', [
            'table_name' => 'leads',
            'record_id' => $lead->id,
            'submitted_by' => $this->admin->id,
        ]);
    }

    public function test_approve_lead_request_preserves_existing_assignments_and_adds_requested_user_once(): void
    {
        $lead = $this->createLead('Customer Four');
        $this->assignLead($lead->id, $this->ownerA->id);
        $this->assignLead($lead->id, $this->ownerD->id);

        $pending = PendingUpdate::create([
            'table_name' => 'leads',
            'record_id' => $lead->id,
            'old_values' => [],
            'new_values' => [],
            'status' => 'pending',
            'submitted_by' => $this->ownerB->id,
        ]);

        $response = $this->callLeadApprove($pending->id, $this->admin, true);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('approved', $pending->fresh()->status);
        $this->assertSame(1, DB::table('lead_user')->where('lead_id', $lead->id)->where('user_id', $this->ownerA->id)->count());
        $this->assertSame(1, DB::table('lead_user')->where('lead_id', $lead->id)->where('user_id', $this->ownerD->id)->count());
        $this->assertSame(1, DB::table('lead_user')->where('lead_id', $lead->id)->where('user_id', $this->ownerB->id)->count());
    }

    public function test_double_approval_is_idempotent(): void
    {
        $lead = $this->createLead('Customer Five');
        $this->assignLead($lead->id, $this->ownerA->id);

        $pending = PendingUpdate::create([
            'table_name' => 'leads',
            'record_id' => $lead->id,
            'old_values' => [],
            'new_values' => [],
            'status' => 'pending',
            'submitted_by' => $this->ownerB->id,
        ]);

        $first = $this->callLeadApprove($pending->id, $this->admin, true);
        $second = $this->callLeadApprove($pending->id, $this->admin, true);

        $this->assertSame(302, $first->getStatusCode());
        $this->assertSame(302, $second->getStatusCode());
        $this->assertSame(1, DB::table('lead_user')->where('lead_id', $lead->id)->where('user_id', $this->ownerB->id)->count());
    }

    public function test_approve_then_reject_returns_conflict_and_does_not_mutate_state(): void
    {
        $lead = $this->createLead('Customer Six');
        $this->assignLead($lead->id, $this->ownerA->id);

        $pending = PendingUpdate::create([
            'table_name' => 'leads',
            'record_id' => $lead->id,
            'old_values' => [],
            'new_values' => [],
            'status' => 'pending',
            'submitted_by' => $this->ownerB->id,
        ]);

        $this->callLeadApprove($pending->id, $this->admin, true);
        $exception = $this->callLeadReject($pending->id, $this->admin, true);

        $this->assertInstanceOf(HttpExceptionInterface::class, $exception);
        $this->assertSame(409, $exception->getStatusCode());
        $this->assertSame('approved', $pending->fresh()->status);
    }

    public function test_cross_domain_finance_endpoint_cannot_process_lead_pending_request(): void
    {
        $lead = $this->createLead('Customer Seven');
        $pending = PendingUpdate::create([
            'table_name' => 'leads',
            'record_id' => $lead->id,
            'old_values' => [],
            'new_values' => [],
            'status' => 'pending',
            'submitted_by' => $this->ownerB->id,
        ]);

        $exception = $this->callFinanceApprove($pending->id, $this->admin, true, true);

        $this->assertInstanceOf(HttpExceptionInterface::class, $exception);
        $this->assertSame(404, $exception->getStatusCode());
        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_unauthorized_user_cannot_access_or_approve_lead_requests(): void
    {
        $lead = $this->createLead('Customer Eight');
        $pending = PendingUpdate::create([
            'table_name' => 'leads',
            'record_id' => $lead->id,
            'old_values' => [],
            'new_values' => [],
            'status' => 'pending',
            'submitted_by' => $this->ownerB->id,
        ]);

        $pageException = $this->callLeadApprovalIndex($this->admin, false);
        $actionException = $this->callLeadApprove($pending->id, $this->admin, false);

        $this->assertInstanceOf(HttpExceptionInterface::class, $pageException);
        $this->assertSame(403, $pageException->getStatusCode());
        $this->assertInstanceOf(HttpExceptionInterface::class, $actionException);
        $this->assertSame(403, $actionException->getStatusCode());
    }

    public function test_stored_xss_values_are_escaped_in_lead_approval_page_markup(): void
    {
        $lead = $this->createLead('<img src=x onerror=alert(1)>');
        Work::create([
            'lead_id' => $lead->id,
            'comment' => '<script>alert(1)</script>',
            'user_id' => $this->ownerA->id,
            'created_at' => '2026-08-10 10:30:00',
            'updated_at' => '2026-08-10 10:30:00',
        ]);

        PendingUpdate::create([
            'table_name' => 'leads',
            'record_id' => $lead->id,
            'old_values' => [],
            'new_values' => [],
            'status' => 'pending',
            'submitted_by' => $this->ownerB->id,
        ]);

        $view = $this->callLeadApprovalIndex($this->admin, true);
        app('view')->share('errors', new ViewErrorBag());
        $html = $view->render();

        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    protected function createSchema(): void
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

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->string('ext')->nullable();
            $table->text('value')->nullable();
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
            $table->string('phone_number')->nullable()->unique();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->integer('is_active')->default(1);
            $table->integer('create_by')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_user', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });

        Schema::create('works', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->longText('comment')->nullable();
            $table->string('call_duration')->nullable();
            $table->integer('call_status')->nullable();
            $table->string('recording_path')->nullable();
            $table->integer('type')->default(1);
            $table->integer('user_id')->nullable();
            $table->timestamp('follow_up')->nullable();
            $table->timestamps();
        });

        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('detail')->nullable();
            $table->integer('is_active')->default(1);
            $table->timestamps();
        });

        Schema::create('customer_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('description')->nullable();
            $table->integer('is_active')->default(1);
            $table->timestamps();
        });

        Schema::create('pending_updates', function (Blueprint $table) {
            $table->id();
            $table->string('table_name');
            $table->unsignedBigInteger('record_id');
            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
        });
    }

    protected function seedBaseData(): void
    {
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->ownerA = User::create([
            'name' => 'Owner A',
            'email' => 'ownera@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->ownerB = User::create([
            'name' => 'Owner B',
            'email' => 'ownerb@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->ownerC = User::create([
            'name' => 'Owner C',
            'email' => 'ownerc@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->ownerD = User::create([
            'name' => 'Owner D',
            'email' => 'ownerd@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->project = Project::create([
            'project' => 'Blue Project',
            'address' => 'Address',
            'is_active' => 1,
            'create_by' => $this->admin->id,
        ]);

        DB::table('settings')->insert([
            [
                'key' => 'app_name',
                'name' => 'Blue Marketing',
                'value' => 'Blue Marketing',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'app_favicon',
                'name' => 'App Favicon',
                'value' => 'images/logo/blue-marketing-logo.png',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'app_logo',
                'name' => 'App Logo',
                'value' => 'images/logo/blue-marketing-logo.png',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'app_short_name',
                'name' => 'Blue',
                'value' => 'Blue',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'app_loading_gif',
                'name' => 'App Loading Gif',
                'value' => 'images/logo/blue-marketing-logo.png',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    protected function createLead(string $name): Lead
    {
        return Lead::create([
            'first_name' => $name,
            'phone_number' => '03' . str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
            'project_id' => $this->project->id,
            'is_active' => 1,
            'create_by' => $this->admin->id,
        ]);
    }

    protected function assignLead(int $leadId, int $userId): void
    {
        DB::table('lead_user')->insert([
            'lead_id' => $leadId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function callLeadApprovalIndex(User $user, bool $authorized)
    {
        Auth::setUser($this->makeLeadApprovalUser($user, $authorized));

        try {
            return app(LeadController::class)->approvalIndex();
        } catch (\Throwable $exception) {
            return $exception;
        }
    }

    protected function callLeadApprove(int $pendingId, User $user, bool $authorized)
    {
        $request = Request::create("/admin/approvals/leads/{$pendingId}/approve", 'POST');

        $session = app('session.store');
        $session->start();
        $session->setPreviousUrl('/admin/approvals/leads');
        $request->setLaravelSession($session);

        $this->app->instance('request', $request);
        Auth::setUser($this->makeLeadApprovalUser($user, $authorized));

        try {
            return app(LeadController::class)->approvePendingAssignment($pendingId, $request);
        } catch (\Throwable $exception) {
            return $exception;
        }
    }

    protected function callLeadReject(int $pendingId, User $user, bool $authorized)
    {
        $request = Request::create("/admin/approvals/leads/{$pendingId}/reject", 'POST');

        $session = app('session.store');
        $session->start();
        $session->setPreviousUrl('/admin/approvals/leads');
        $request->setLaravelSession($session);

        $this->app->instance('request', $request);
        Auth::setUser($this->makeLeadApprovalUser($user, $authorized));

        try {
            return app(LeadController::class)->rejectPendingAssignment($pendingId, $request);
        } catch (\Throwable $exception) {
            return $exception;
        }
    }

    protected function callFinanceApprove(int $pendingId, User $user, bool $canReadVoucher, bool $canDirectUpdate)
    {
        $request = Request::create("/admin/approvals/finance/{$pendingId}/approve", 'POST');

        $session = app('session.store');
        $session->start();
        $session->setPreviousUrl('/admin/approvals/finance');
        $request->setLaravelSession($session);

        $this->app->instance('request', $request);
        Auth::setUser($this->makeFinanceApprovalUser($user, $canReadVoucher, $canDirectUpdate));

        try {
            return app(VoucherController::class)->approveFinancePending($pendingId, $request);
        } catch (\Throwable $exception) {
            return $exception;
        }
    }

    protected function makeLeadApprovalUser(User $user, bool $authorized)
    {
        $mockUser = Mockery::mock($user)->makePartial();
        $mockUser->shouldIgnoreMissing();
        $mockUser->setRelation('roles', new Collection([(object) ['name' => 'Admin']]));
        $mockUser->shouldReceive('can')->with('update lead')->andReturn($authorized);
        $mockUser->shouldReceive('hasAnyRole')->with(['admin', 'superadmin', 'super-admin'])->andReturn($authorized);

        return $mockUser;
    }

    protected function makeFinanceApprovalUser(User $user, bool $canReadVoucher, bool $canDirectUpdate)
    {
        $mockUser = Mockery::mock($user)->makePartial();
        $mockUser->shouldIgnoreMissing();
        $mockUser->setRelation('roles', new Collection([(object) ['name' => 'Admin']]));
        $mockUser->shouldReceive('can')->with('read voucher')->andReturn($canReadVoucher);
        $mockUser->shouldReceive('can')->with('direct-update')->andReturn($canDirectUpdate);
        $mockUser->shouldReceive('hasRole')->with('super-admin')->andReturnFalse();

        return $mockUser;
    }
}
