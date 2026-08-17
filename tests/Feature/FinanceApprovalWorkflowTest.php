<?php

namespace Tests\Feature;

use App\Http\Controllers\Finance\VoucherController;
use App\Http\Controllers\LeadController;
use App\Models\PendingUpdate;
use App\Models\User;
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

class FinanceApprovalWorkflowTest extends TestCase
{
    private User $admin;

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

    public function test_finance_approval_page_filters_to_finance_tables_only(): void
    {
        PendingUpdate::create([
            'table_name' => 'ledgers',
            'record_id' => 1,
            'old_values' => ['detail' => 'old ledger'],
            'new_values' => ['detail' => 'new ledger'],
            'status' => 'pending',
            'submitted_by' => $this->admin->id,
        ]);

        PendingUpdate::create([
            'table_name' => 'draft_ledgers',
            'record_id' => 2,
            'old_values' => ['detail' => 'old draft'],
            'new_values' => ['detail' => 'new draft'],
            'status' => 'pending',
            'submitted_by' => $this->admin->id,
        ]);

        PendingUpdate::create([
            'table_name' => 'customer_ledger',
            'record_id' => 3,
            'old_values' => ['description' => 'old customer ledger'],
            'new_values' => ['description' => 'new customer ledger'],
            'status' => 'pending',
            'submitted_by' => $this->admin->id,
        ]);

        PendingUpdate::create([
            'table_name' => 'leads',
            'record_id' => 4,
            'old_values' => [],
            'new_values' => [],
            'status' => 'pending',
            'submitted_by' => $this->admin->id,
        ]);

        $view = $this->callFinanceApprovalIndex(true, true);
        $tableNames = $view->getData()['pendingUpdates']->pluck('table_name')->all();

        sort($tableNames);
        $this->assertSame(['customer_ledger', 'draft_ledgers', 'ledgers'], array_values(array_unique($tableNames)));
        $this->assertNotContains('leads', $tableNames);
    }

    public function test_lead_endpoint_cannot_process_finance_pending_request(): void
    {
        $pending = PendingUpdate::create([
            'table_name' => 'ledgers',
            'record_id' => 1,
            'old_values' => ['detail' => 'old ledger'],
            'new_values' => ['detail' => 'new ledger'],
            'status' => 'pending',
            'submitted_by' => $this->admin->id,
        ]);

        $exception = $this->callLeadApprove($pending->id, true);

        $this->assertInstanceOf(HttpExceptionInterface::class, $exception);
        $this->assertSame(404, $exception->getStatusCode());
        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_finance_pages_and_actions_reject_unauthorized_users(): void
    {
        $pending = PendingUpdate::create([
            'table_name' => 'ledgers',
            'record_id' => 1,
            'old_values' => ['detail' => 'old ledger'],
            'new_values' => ['detail' => 'new ledger'],
            'status' => 'pending',
            'submitted_by' => $this->admin->id,
        ]);

        $pageException = $this->callFinanceApprovalIndex(false, false);
        $actionException = $this->callFinanceApprove($pending->id, false, false);

        $this->assertInstanceOf(HttpExceptionInterface::class, $pageException);
        $this->assertSame(403, $pageException->getStatusCode());
        $this->assertInstanceOf(HttpExceptionInterface::class, $actionException);
        $this->assertSame(403, $actionException->getStatusCode());
        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_user_with_only_read_voucher_cannot_access_finance_change_approvals(): void
    {
        $exception = $this->callFinanceApprovalIndex(true, false, false);

        $this->assertInstanceOf(HttpExceptionInterface::class, $exception);
        $this->assertSame(403, $exception->getStatusCode());
    }

    public function test_direct_update_user_can_access_finance_change_approvals(): void
    {
        $view = $this->callFinanceApprovalIndex(true, true, false);

        $this->assertInstanceOf(\Illuminate\View\View::class, $view);
    }

    public function test_super_admin_user_can_access_finance_change_approvals(): void
    {
        $view = $this->callFinanceApprovalIndex(true, false, true);

        $this->assertInstanceOf(\Illuminate\View\View::class, $view);
    }

    public function test_old_pending_updates_route_redirects_to_finance_approvals(): void
    {
        $response = app(VoucherController::class)->pendingIndex();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('approvals.finance.index'), $response->getTargetUrl());
    }

    public function test_finance_approval_page_escapes_stored_xss_values_in_markup(): void
    {
        PendingUpdate::create([
            'table_name' => 'ledgers',
            'record_id' => 1,
            'old_values' => ['detail' => '<script>alert(1)</script>', 'name' => 'O\'Connor', 'quoted' => '"Ali"', 'symbols' => '& < >'],
            'new_values' => ['detail' => '\' onmouseover=\'alert(1)', 'note' => '" autofocus onfocus="alert(1)', 'name' => 'O\'Connor', 'quoted' => '"Ali"', 'symbols' => '& < >'],
            'status' => 'pending',
            'submitted_by' => $this->admin->id,
        ]);

        $view = $this->callFinanceApprovalIndex(true, true, false);
        app('view')->share('errors', new ViewErrorBag());
        $html = $view->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('data-old=', $html);
        $this->assertStringNotContainsString('data-new=', $html);
        $this->assertStringNotContainsString('\' onmouseover=\'alert(1)', $html);
        $this->assertStringNotContainsString('" autofocus onfocus="alert(1)', $html);
        $this->assertStringContainsString('finance-approval-payload-', $html);

        $payload = $this->extractJsonScriptPayload($html, 'finance-approval-payload-1');

        $this->assertIsArray($payload);
        $this->assertSame('<script>alert(1)</script>', $payload['old']['detail']);
        $this->assertSame('\' onmouseover=\'alert(1)', $payload['new']['detail']);
        $this->assertSame('" autofocus onfocus="alert(1)', $payload['new']['note']);
        $this->assertSame('O\'Connor', $payload['new']['name']);
        $this->assertSame('"Ali"', $payload['new']['quoted']);
        $this->assertSame('& < >', $payload['new']['symbols']);
    }

    public function test_finance_approve_is_idempotent(): void
    {
        $pending = PendingUpdate::create([
            'table_name' => 'ledgers',
            'record_id' => 1,
            'old_values' => ['detail' => 'Ledger 1'],
            'new_values' => ['detail' => 'Approved detail'],
            'status' => 'pending',
            'submitted_by' => $this->admin->id,
        ]);

        $first = $this->callFinanceApprove($pending->id, true, true, false);
        $second = $this->callFinanceApprove($pending->id, true, true, false);

        $this->assertSame(302, $first->getStatusCode());
        $this->assertSame(302, $second->getStatusCode());
        $this->assertSame('approved', $pending->fresh()->status);
        $this->assertSame('Approved detail', DB::table('ledgers')->where('id', 1)->value('detail'));
    }

    public function test_finance_approved_request_cannot_later_be_rejected(): void
    {
        $pending = PendingUpdate::create([
            'table_name' => 'ledgers',
            'record_id' => 1,
            'old_values' => ['detail' => 'Ledger 1'],
            'new_values' => ['detail' => 'Approved detail'],
            'status' => 'pending',
            'submitted_by' => $this->admin->id,
        ]);

        $this->callFinanceApprove($pending->id, true, true, false);
        $exception = $this->callFinanceReject($pending->id, true, true, false);

        $this->assertInstanceOf(HttpExceptionInterface::class, $exception);
        $this->assertSame(409, $exception->getStatusCode());
        $this->assertSame('approved', $pending->fresh()->status);
        $this->assertSame('Approved detail', DB::table('ledgers')->where('id', 1)->value('detail'));
    }

    public function test_finance_rejected_request_cannot_later_be_approved(): void
    {
        $pending = PendingUpdate::create([
            'table_name' => 'ledgers',
            'record_id' => 1,
            'old_values' => ['detail' => 'Ledger 1'],
            'new_values' => ['detail' => 'Approved detail'],
            'status' => 'pending',
            'submitted_by' => $this->admin->id,
        ]);

        $this->callFinanceReject($pending->id, true, true, false);
        $exception = $this->callFinanceApprove($pending->id, true, true, false);

        $this->assertInstanceOf(HttpExceptionInterface::class, $exception);
        $this->assertSame(409, $exception->getStatusCode());
        $this->assertSame('rejected', $pending->fresh()->status);
        $this->assertSame('Ledger 1', DB::table('ledgers')->where('id', 1)->value('detail'));
    }

    public function test_finance_reject_is_idempotent(): void
    {
        $pending = PendingUpdate::create([
            'table_name' => 'draft_ledgers',
            'record_id' => 2,
            'old_values' => ['detail' => 'Draft 2'],
            'new_values' => ['detail' => 'Rejected detail'],
            'status' => 'pending',
            'submitted_by' => $this->admin->id,
        ]);

        $first = $this->callFinanceReject($pending->id, true, true, false);
        $second = $this->callFinanceReject($pending->id, true, true, false);

        $this->assertSame(302, $first->getStatusCode());
        $this->assertSame(302, $second->getStatusCode());
        $this->assertSame('rejected', $pending->fresh()->status);
        $this->assertSame('Draft 2', DB::table('draft_ledgers')->where('id', 2)->value('detail'));
    }

    public function test_sidebar_visibility_follows_centralized_finance_authorization_rule(): void
    {
        $this->assertFalse(VoucherController::canAccessFinanceApprovalsForUser($this->makeFinanceUser(true, false, false)));
        $this->assertTrue(VoucherController::canAccessFinanceApprovalsForUser($this->makeFinanceUser(true, true, false)));
        $this->assertTrue(VoucherController::canAccessFinanceApprovalsForUser($this->makeFinanceUser(true, false, true)));

        Auth::setUser($this->makeFinanceUser(true, false, false));
        $html = view('admin.layouts.sidebar', ['modulemenus' => []])->render();
        $this->assertStringNotContainsString('Finance Change Approvals', $html);

        Auth::setUser($this->makeFinanceUser(true, true, false));
        $html = view('admin.layouts.sidebar', ['modulemenus' => []])->render();
        $this->assertStringContainsString('Finance Change Approvals', $html);
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

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->integer('is_active')->default(1);
            $table->timestamps();
        });

        Schema::create('lead_user', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });

        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('detail')->nullable();
            $table->integer('is_active')->default(1);
            $table->timestamps();
        });

        Schema::create('draft_ledgers', function (Blueprint $table) {
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
            'name' => 'Finance Admin',
            'email' => 'finance-admin@example.com',
            'password' => Hash::make('password'),
        ]);

        DB::table('ledgers')->insert([
            'id' => 1,
            'detail' => 'Ledger 1',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('draft_ledgers')->insert([
            'id' => 2,
            'detail' => 'Draft 2',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('customer_ledger')->insert([
            'id' => 3,
            'description' => 'Customer Ledger 3',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
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

    protected function callFinanceApprovalIndex(bool $canReadVoucher, bool $canDirectUpdate, bool $isSuperAdmin = false)
    {
        Auth::setUser($this->makeFinanceUser($canReadVoucher, $canDirectUpdate, $isSuperAdmin));

        try {
            return app(VoucherController::class)->financeApprovalIndex();
        } catch (\Throwable $exception) {
            return $exception;
        }
    }

    protected function callFinanceApprove(int $pendingId, bool $canReadVoucher, bool $canDirectUpdate, bool $isSuperAdmin = false)
    {
        $request = Request::create("/admin/approvals/finance/{$pendingId}/approve", 'POST');

        $session = app('session.store');
        $session->start();
        $session->setPreviousUrl('/admin/approvals/finance');
        $request->setLaravelSession($session);

        $this->app->instance('request', $request);
        Auth::setUser($this->makeFinanceUser($canReadVoucher, $canDirectUpdate, $isSuperAdmin));

        try {
            return app(VoucherController::class)->approveFinancePending($pendingId, $request);
        } catch (\Throwable $exception) {
            return $exception;
        }
    }

    protected function callFinanceReject(int $pendingId, bool $canReadVoucher, bool $canDirectUpdate, bool $isSuperAdmin = false)
    {
        $request = Request::create("/admin/approvals/finance/{$pendingId}/reject", 'POST');

        $session = app('session.store');
        $session->start();
        $session->setPreviousUrl('/admin/approvals/finance');
        $request->setLaravelSession($session);

        $this->app->instance('request', $request);
        Auth::setUser($this->makeFinanceUser($canReadVoucher, $canDirectUpdate, $isSuperAdmin));

        try {
            return app(VoucherController::class)->rejectFinancePending($pendingId, $request);
        } catch (\Throwable $exception) {
            return $exception;
        }
    }

    protected function callLeadApprove(int $pendingId, bool $authorized)
    {
        $request = Request::create("/admin/approvals/leads/{$pendingId}/approve", 'POST');

        $session = app('session.store');
        $session->start();
        $session->setPreviousUrl('/admin/approvals/leads');
        $request->setLaravelSession($session);

        $this->app->instance('request', $request);

        $mockUser = Mockery::mock($this->admin)->makePartial();
        $mockUser->shouldIgnoreMissing();
        $mockUser->setRelation('roles', new Collection([(object) ['name' => 'Admin']]));
        $mockUser->shouldReceive('can')->with('update lead')->andReturn($authorized);
        $mockUser->shouldReceive('hasAnyRole')->with(['admin', 'superadmin', 'super-admin'])->andReturn($authorized);
        Auth::setUser($mockUser);

        try {
            return app(LeadController::class)->approvePendingAssignment($pendingId, $request);
        } catch (\Throwable $exception) {
            return $exception;
        }
    }

    protected function makeFinanceUser(bool $canReadVoucher, bool $canDirectUpdate, bool $isSuperAdmin = false)
    {
        $mockUser = Mockery::mock($this->admin)->makePartial();
        $mockUser->shouldIgnoreMissing();
        $mockUser->setRelation('roles', new Collection([(object) ['name' => 'Admin']]));
        $mockUser->shouldReceive('can')->with('read voucher')->andReturn($canReadVoucher);
        $mockUser->shouldReceive('can')->with('direct-update')->andReturn($canDirectUpdate);
        $mockUser->shouldReceive('hasRole')->with('super-admin')->andReturn($isSuperAdmin);

        return $mockUser;
    }

    protected function extractJsonScriptPayload(string $html, string $scriptId): array
    {
        $pattern = '/<script type="application\/json" id="' . preg_quote($scriptId, '/') . '">\s*(.*?)\s*<\/script>/s';
        $this->assertSame(1, preg_match($pattern, $html, $matches), 'JSON payload script tag not found.');
        $this->assertStringNotContainsString('&quot;', $matches[1]);

        $payload = json_decode(trim($matches[1]), true);
        $this->assertIsArray($payload, 'Rendered script content must be valid JSON.');

        return $payload;
    }
}
