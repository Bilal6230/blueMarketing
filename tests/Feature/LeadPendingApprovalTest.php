<?php

namespace Tests\Feature;

use App\Http\Controllers\Finance\VoucherController;
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

    public function test_pending_index_uses_actual_comment_authors(): void
    {
        $lead = $this->createLead('Customer One');
        $this->assignLead($lead->id, $this->ownerA->id);

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

        $view = $this->callPendingIndexAsAdmin();
        $comments = $view->getData()['leadCommentsByRecord']->get($lead->id);

        $this->assertSame('Sale Done', $comments[0]['comment']);
        $this->assertSame($this->ownerB->name, $comments[0]['user_name']);
        $this->assertSame('Visit the project', $comments[1]['comment']);
        $this->assertSame($this->ownerA->name, $comments[1]['user_name']);
        $this->assertNotSame($this->ownerC->name, $comments[0]['user_name']);
        $this->assertNotSame($this->ownerC->name, $comments[1]['user_name']);
    }

    public function test_pending_index_exposes_current_and_requested_owner_for_leads(): void
    {
        $lead = $this->createLead('Customer Two');
        $this->assignLead($lead->id, $this->ownerA->id);

        PendingUpdate::create([
            'table_name' => 'leads',
            'record_id' => $lead->id,
            'old_values' => [],
            'new_values' => [],
            'status' => 'pending',
            'submitted_by' => $this->ownerB->id,
        ]);

        $view = $this->callPendingIndexAsAdmin();
        $pending = $view->getData()['pendingUpdates']->first();

        $this->assertSame($this->ownerA->name, $pending->current_owner_name);
        $this->assertSame($this->ownerB->name, $pending->requested_owner_name);
        $this->assertSame('Customer Two', $pending->lead_name);
        $this->assertSame('Lead Assignment / Ownership Request', $pending->request_type);
    }

    public function test_approve_lead_request_marks_pending_approved_and_preserves_unrelated_assignments(): void
    {
        $lead = $this->createLead('Customer Three');
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

        $response = $this->callApproveAdmin($pending->id, 'leads', $this->admin, true);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('approved', $pending->fresh()->status);
        $this->assertSame($this->admin->id, (int) $pending->fresh()->approved_by);
        $this->assertSame(1, DB::table('lead_user')->where('lead_id', $lead->id)->where('user_id', $this->ownerB->id)->count());
        $this->assertSame(1, DB::table('lead_user')->where('lead_id', $lead->id)->where('user_id', $this->ownerA->id)->count());
        $this->assertSame(1, DB::table('lead_user')->where('lead_id', $lead->id)->where('user_id', $this->ownerD->id)->count());
        $this->assertSame(3, DB::table('lead_user')->where('lead_id', $lead->id)->count());
    }

    public function test_double_approval_is_idempotent_and_does_not_duplicate_assignments(): void
    {
        $lead = $this->createLead('Customer Four');
        $this->assignLead($lead->id, $this->ownerA->id);

        $pending = PendingUpdate::create([
            'table_name' => 'leads',
            'record_id' => $lead->id,
            'old_values' => [],
            'new_values' => [],
            'status' => 'pending',
            'submitted_by' => $this->ownerB->id,
        ]);

        $first = $this->callApproveAdmin($pending->id, 'leads', $this->admin, true);
        $second = $this->callApproveAdmin($pending->id, 'leads', $this->admin, true);

        $this->assertSame(302, $first->getStatusCode());
        $this->assertSame(302, $second->getStatusCode());
        $this->assertSame('approved', $pending->fresh()->status);
        $this->assertSame(1, DB::table('lead_user')->where('lead_id', $lead->id)->where('user_id', $this->ownerB->id)->count());
        $this->assertSame(2, DB::table('lead_user')->where('lead_id', $lead->id)->count());
    }

    public function test_unauthorized_user_cannot_approve_lead_request(): void
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

        $exception = $this->callApproveAdmin($pending->id, 'leads', $this->admin, false);

        $this->assertInstanceOf(HttpExceptionInterface::class, $exception);
        $this->assertSame(403, $exception->getStatusCode());
        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame(1, DB::table('lead_user')->where('lead_id', $lead->id)->count());
    }

    public function test_tampered_table_request_is_rejected_and_lead_assignment_is_not_modified(): void
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

        $exception = $this->callApproveAdmin($pending->id, 'ledgers', $this->admin, true);

        $this->assertInstanceOf(HttpExceptionInterface::class, $exception);
        $this->assertSame(422, $exception->getStatusCode());
        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame(1, DB::table('lead_user')->where('lead_id', $lead->id)->where('user_id', $this->ownerA->id)->count());
        $this->assertSame(0, DB::table('lead_user')->where('lead_id', $lead->id)->where('user_id', $this->ownerB->id)->count());
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

    protected function callPendingIndexAsAdmin()
    {
        Auth::setUser($this->makeAuthorizedUser($this->admin, true));

        return app(VoucherController::class)->pendingIndex();
    }

    protected function callApproveAdmin(int $pendingId, string $table, User $user, bool $authorized)
    {
        $request = Request::create("/admin/finance/voucher/{$pendingId}/approveadmin", 'POST', [
            'table' => $table,
        ]);

        $session = app('session.store');
        $session->start();
        $session->setPreviousUrl('/admin/finance/voucher/pending_updates');
        $request->setLaravelSession($session);

        $this->app->instance('request', $request);
        Auth::setUser($this->makeAuthorizedUser($user, $authorized));

        try {
            return app(VoucherController::class)->approveAdmin($pendingId, $request);
        } catch (\Throwable $exception) {
            return $exception;
        }
    }

    protected function makeAuthorizedUser(User $user, bool $authorized)
    {
        $mockUser = Mockery::mock($user)->makePartial();
        $mockUser->setRelation('roles', new Collection([(object) ['name' => 'Admin']]));
        $mockUser->shouldReceive('hasRole')->andReturnFalse();
        $mockUser->shouldReceive('can')->with('direct-update')->andReturn($authorized);

        return $mockUser;
    }
}
