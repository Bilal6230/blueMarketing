<?php

namespace Tests\Feature\Finance;

use App\Models\DailyVoucherApproval;
use App\Services\Finance\DailyVoucherApprovalWorkflow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DailyVoucherApprovalWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropAllTables();
        $migration = require database_path('migrations/2026_11_05_000001_create_daily_voucher_approval_workflow_tables.php');
        $migration->up();
        // This isolated test wipes all tables. Recreate the minimum authentic
        // user/role schema required by the live VoucherApproverAccess policy;
        // never bypass authorization or modify production approval rules.
        Schema::create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->unsignedBigInteger('project_id');
            $t->unsignedInteger('status_id');
        });
        Schema::create('roles', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
        });
        Schema::create('model_has_roles', function (Blueprint $t): void {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->primary(['role_id', 'model_id', 'model_type']);
        });
        DB::table('users')->insert([
            ['id'=>10,'name'=>'Voucher Submitter','project_id'=>1,'status_id'=>1],
            ['id'=>20,'name'=>'Authorized Super Admin','project_id'=>1,'status_id'=>1],
        ]);
        DB::table('roles')->insert([
            'id'=>1,'name'=>'superadmin','guard_name'=>'web',
        ]);
        DB::table('model_has_roles')->insert([
            'role_id'=>1,'model_type'=>\\App\\Models\\User::class,'model_id'=>20,
        ]);
        Schema::create('customer_ledger', function (Blueprint $t): void { $t->id(); $t->unsignedBigInteger('project_id'); $t->boolean('is_active')->default(false); $t->integer('is_approve')->default(0); });
        Schema::create('project_head_subheads', function (Blueprint $t): void { $t->id(); $t->unsignedBigInteger('project_id'); });
        Schema::create('ledgers', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('customer_ledger_id')->nullable(); $t->string('type', 12); $t->unsignedBigInteger('type_id');
            $t->unsignedBigInteger('project_head_subheads_id'); $t->boolean('is_active')->default(false);
        });
        Schema::create('journal_vouchers', function (Blueprint $t): void { $t->id(); $t->unsignedBigInteger('project_id'); $t->string('status'); $t->timestamps(); });
        DB::table('daily_voucher_approval_policies')->insert([
            'project_id' => 1, 'voucher_type' => 'CR', 'approval_required' => true, 'approver_user_id' => 20, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_submitter_cannot_approve_their_own_voucher(): void
    {
        DB::table('customer_ledger')->insert(['id' => 7, 'project_id' => 1, 'is_active' => 0]);
        DB::table('project_head_subheads')->insert(['id' => 11, 'project_id' => 1]);
        DB::table('project_head_subheads')->insert(['id' => 12, 'project_id' => 2]);
        DB::table('ledgers')->insert([
            ['id' => 31, 'customer_ledger_id' => 7, 'type' => 'CR', 'type_id' => 7, 'project_head_subheads_id' => 11, 'is_active' => 0],
            ['id' => 32, 'customer_ledger_id' => 7, 'type' => 'CR', 'type_id' => 7, 'project_head_subheads_id' => 12, 'is_active' => 0],
        ]);
        $workflow = app(DailyVoucherApprovalWorkflow::class);
        $this->assertTrue($workflow->submitIfRequired(1, 'CR', 'customer_ledger', 7, 10));
        $approval = DailyVoucherApproval::query()->firstOrFail();
        $this->expectException(\DomainException::class);
        try { $workflow->decide($approval, 10, 'approved'); } finally {
            $this->assertSame(0, (int) DB::table('ledgers')->where('id', 31)->value('is_active'));
        }
    }

    public function test_assigned_approver_activates_only_project_owned_receipt_lines_and_records_events(): void
    {
        DB::table('customer_ledger')->insert(['id' => 7, 'project_id' => 1, 'is_active' => 0]);
        DB::table('project_head_subheads')->insert([['id' => 11, 'project_id' => 1], ['id' => 12, 'project_id' => 2]]);
        DB::table('ledgers')->insert([
            ['id' => 31, 'customer_ledger_id' => 7, 'type' => 'CR', 'type_id' => 7, 'project_head_subheads_id' => 11, 'is_active' => 0],
            ['id' => 32, 'customer_ledger_id' => 7, 'type' => 'CR', 'type_id' => 7, 'project_head_subheads_id' => 12, 'is_active' => 0],
        ]);
        $workflow = app(DailyVoucherApprovalWorkflow::class);
        $workflow->submitIfRequired(1, 'CR', 'customer_ledger', 7, 10);
        $workflow->decide(DailyVoucherApproval::query()->firstOrFail(), 20, 'approved');
        $this->assertSame(1, (int) DB::table('customer_ledger')->where('id', 7)->value('is_active'));
        $this->assertSame(1, (int) DB::table('ledgers')->where('id', 31)->value('is_active'));
        $this->assertSame(0, (int) DB::table('ledgers')->where('id', 32)->value('is_active'));
        $this->assertSame(['submitted', 'approved'], DB::table('daily_voucher_approval_events')->orderBy('id')->pluck('event')->all());
    }

    public function test_rejection_requires_a_reason_and_keeps_the_voucher_unposted(): void
    {
        DB::table('customer_ledger')->insert(['id' => 7, 'project_id' => 1, 'is_active' => 0]);
        DB::table('project_head_subheads')->insert(['id' => 11, 'project_id' => 1]);
        DB::table('ledgers')->insert(['id' => 31, 'customer_ledger_id' => 7, 'type' => 'CR', 'type_id' => 7, 'project_head_subheads_id' => 11, 'is_active' => 0]);
        $workflow = app(DailyVoucherApprovalWorkflow::class);
        $workflow->submitIfRequired(1, 'CR', 'customer_ledger', 7, 10);
        $approval = DailyVoucherApproval::query()->firstOrFail();
        try {
            $workflow->decide($approval, 20, 'rejected');
            $this->fail('A rejection without a reason must be refused.');
        } catch (\DomainException $e) {
            $this->assertSame('DAILY_VOUCHER_REJECTION_REASON_REQUIRED', $e->getMessage());
        }
        $this->assertSame('pending', $approval->fresh()->status);
        $this->assertSame(0, (int) DB::table('ledgers')->where('id', 31)->value('is_active'));
    }

    public function test_clearance_ledger_source_is_project_scoped_and_can_be_approved(): void
    {
        DB::table('daily_voucher_approval_policies')->where('voucher_type', 'CR')->update(['voucher_type' => 'CP']);
        DB::table('project_head_subheads')->insert([['id' => 11, 'project_id' => 1], ['id' => 12, 'project_id' => 2]]);
        DB::table('ledgers')->insert([
            ['id' => 41, 'customer_ledger_id' => 70, 'type' => 'CP', 'type_id' => 70, 'project_head_subheads_id' => 11, 'is_active' => 0],
            ['id' => 42, 'customer_ledger_id' => 70, 'type' => 'CP', 'type_id' => 70, 'project_head_subheads_id' => 12, 'is_active' => 0],
        ]);
        $workflow = app(DailyVoucherApprovalWorkflow::class);
        $workflow->submitIfRequired(1, 'CP', 'ledgers', 41, 10);
        $workflow->decide(DailyVoucherApproval::query()->firstOrFail(), 20, 'approved');
        $this->assertSame(1, (int) DB::table('ledgers')->where('id', 41)->value('is_active'));
        $this->assertSame(0, (int) DB::table('ledgers')->where('id', 42)->value('is_active'));
    }
}
