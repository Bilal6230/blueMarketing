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
        // The old combined migration was replaced by the consolidated schema.
        // These SQLite fixtures match the active approval model's required columns,
        // without requiring MySQL-only baseline migrations.
        Schema::create('daily_voucher_approval_policies', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->string('voucher_type',12);
            $t->boolean('approval_required')->default(false);
            $t->unsignedBigInteger('approver_user_id')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->unique(['project_id','voucher_type']);
        });
        Schema::create('daily_voucher_approvals', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->string('voucher_type',12);
            $t->string('source_table',64);
            $t->unsignedBigInteger('source_id');
            $t->string('status',16)->default('pending');
            $t->unsignedBigInteger('assigned_approver_id');
            $t->unsignedBigInteger('submitted_by');
            $t->unsignedBigInteger('decided_by')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->unique(['source_table','source_id']);
        });
        Schema::create('daily_voucher_approval_events', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('approval_id');
            $t->string('event',24);
            $t->unsignedBigInteger('actor_id');
            $t->text('remarks')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
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
        // Spatie's permission registrar loads the permissions table even when
        // the actor has no direct permissions. Keep it real and empty: user 10
        // must still be denied by the actual authorization policy.
        Schema::create('permissions', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
        });
        Schema::create('role_has_permissions', function (Blueprint $t): void {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
            $t->primary(['permission_id','role_id']);
        });
        Schema::create('model_has_permissions', function (Blueprint $t): void {
            $t->unsignedBigInteger('permission_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->primary(['permission_id','model_id','model_type']);
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
            'role_id'=>1,'model_type'=>\App\Models\User::class,'model_id'=>20,
        ]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Schema::create('customer_ledger', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('transaction_type',12)->default('CR');
            $t->unsignedBigInteger('type_id')->default(0);
            $t->unsignedTinyInteger('payment_type')->default(1);
            $t->decimal('amount_in',18,2)->default(0); $t->decimal('amount_out',18,2)->default(0);
            $t->boolean('is_active')->default(false); $t->integer('is_approve')->default(0);
        });
        Schema::create('project_head_subheads', function (Blueprint $t): void { $t->id(); $t->unsignedBigInteger('project_id'); });
        Schema::create('ledgers', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('customer_ledger_id')->nullable(); $t->string('type', 12); $t->unsignedBigInteger('type_id');
            $t->unsignedBigInteger('project_head_subheads_id');
            $t->unsignedBigInteger('voucher_number')->default(0);
            $t->decimal('amount_in',18,2)->default(0); $t->decimal('amount_out',18,2)->default(0);
            $t->boolean('is_active')->default(false);
        });
        Schema::create('journal_vouchers', function (Blueprint $t): void { $t->id(); $t->unsignedBigInteger('project_id'); $t->string('status'); $t->timestamps(); });
        DB::table('daily_voucher_approval_policies')->insert([
            'project_id' => 1, 'voucher_type' => 'CR', 'approval_required' => true, 'approver_user_id' => 20, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_every_configured_series_is_honored_in_normal_erp_mode(): void
    {
        $workflow = app(DailyVoucherApprovalWorkflow::class);
        self::assertTrue(\App\Services\Finance\FinanceWorkflowAccess::normalErpMode());
        $series = ['CR','CP','CCR','CDR','BOR','BOP','JV','SV','BD','BW','DR','CV','PPR','PP'];
        foreach ($series as $type) {
            DB::table('daily_voucher_approval_policies')->updateOrInsert(
                ['project_id'=>1,'voucher_type'=>$type],
                ['approval_required'=>1,'approver_user_id'=>20,
                    'created_at'=>now(),'updated_at'=>now()]
            );
            self::assertNotNull($workflow->policy(1,$type), $type.' policy must not be bypassed');
            self::assertNull($workflow->policy(2,$type), $type.' policy belongs only to project 1');
        }

        DB::table('daily_voucher_approval_policies')->where('project_id',1)
            ->where('voucher_type','BOR')->update(['approval_required'=>0]);
        self::assertNull($workflow->policy(1,'BOR'),
            'Disabled policy should still use direct posting when explicitly configured off');
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
        $this->assertSame('pending', DB::table('daily_voucher_approvals')
            ->where('source_id',7)->value('status'));
        $approval = DailyVoucherApproval::query()->firstOrFail();
        $this->expectException(\DomainException::class);
        try { $workflow->decide($approval, 10, 'approved'); } finally {
            $this->assertSame(0, (int) DB::table('ledgers')->where('id', 31)->value('is_active'));
        }
    }

    public function test_cross_project_hidden_leg_blocks_approval_without_partial_activation(): void
    {
        DB::table('customer_ledger')->insert([
            'id'=>7,'project_id'=>1,'transaction_type'=>'CR','is_active'=>0,
            'amount_in'=>150,'amount_out'=>0,
        ]);
        DB::table('project_head_subheads')->insert([
            ['id'=>11,'project_id'=>1],['id'=>12,'project_id'=>2],
        ]);
        DB::table('ledgers')->insert([
            ['id'=>31,'customer_ledger_id'=>7,'type'=>'CR','type_id'=>7,
                'project_head_subheads_id'=>11,'amount_in'=>150,'amount_out'=>0,'is_active'=>0],
            ['id'=>32,'customer_ledger_id'=>7,'type'=>'CR','type_id'=>7,
                'project_head_subheads_id'=>12,'amount_in'=>0,'amount_out'=>150,'is_active'=>0],
        ]);
        $workflow=app(DailyVoucherApprovalWorkflow::class);
        $this->seedPreviouslySubmittedApproval('CR','customer_ledger',7);
        try {
            $workflow->decide(DailyVoucherApproval::query()->firstOrFail(),20,'approved');
            $this->fail('A hidden cross-project counter-leg was approved.');
        } catch (\DomainException $e) {
            $this->assertSame('STANDARD_APPROVAL_LEDGER_STATE_INVALID',$e->getMessage());
        }
        $this->assertSame(0,(int)DB::table('customer_ledger')->where('id',7)->value('is_active'));
        $this->assertSame(0,DB::table('ledgers')->where('customer_ledger_id',7)->where('is_active',1)->count());
        $this->assertSame('pending',DB::table('daily_voucher_approvals')->where('source_id',7)->value('status'));
    }

    public function test_assigned_approver_activates_both_balanced_project_owned_receipt_legs(): void
    {
        DB::table('customer_ledger')->insert([
            'id'=>7,'project_id'=>1,'transaction_type'=>'CR','is_active'=>0,
            'amount_in'=>150,'amount_out'=>0,
        ]);
        DB::table('project_head_subheads')->insert([
            ['id'=>11,'project_id'=>1],['id'=>13,'project_id'=>1],
        ]);
        DB::table('ledgers')->insert([
            ['id'=>31,'customer_ledger_id'=>7,'type'=>'CR','type_id'=>7,
                'voucher_number'=>7,'project_head_subheads_id'=>11,
                'amount_in'=>150,'amount_out'=>0,'is_active'=>0],
            ['id'=>32,'customer_ledger_id'=>7,'type'=>'CR','type_id'=>7,
                'voucher_number'=>7,'project_head_subheads_id'=>13,
                'amount_in'=>0,'amount_out'=>150,'is_active'=>0],
        ]);
        $this->seedPreviouslySubmittedApproval('CR','customer_ledger',7);
        app(DailyVoucherApprovalWorkflow::class)
            ->decide(DailyVoucherApproval::query()->firstOrFail(),20,'approved');
        $this->assertSame(1,(int)DB::table('customer_ledger')->where('id',7)->value('is_active'));
        $this->assertSame(2,DB::table('ledgers')->where('customer_ledger_id',7)->where('is_active',1)->count());
        $this->assertSame('approved',DB::table('daily_voucher_approvals')->where('source_id',7)->value('status'));
        $this->assertSame(['submitted','approved'],
            DB::table('daily_voucher_approval_events')->orderBy('id')->pluck('event')->all());
    }

    public function test_both_cash_only_series_activate_two_balanced_legs_on_approval(): void
    {
        foreach (['CCR','CDR'] as $series) {
            $sourceId=$series==='CCR'?71:72;
            $incoming=$series==='CCR';
            DB::table('customer_ledger')->insert([
                'id'=>$sourceId,'project_id'=>1,'transaction_type'=>$series,
                'type_id'=>$sourceId,'payment_type'=>1,
                'amount_in'=>$incoming?125:0,'amount_out'=>$incoming?0:125,
                'is_active'=>0,'is_approve'=>0,
            ]);
            DB::table('project_head_subheads')->insert([
                ['id'=>$sourceId*10+1,'project_id'=>1],
                ['id'=>$sourceId*10+2,'project_id'=>1],
            ]);
            DB::table('ledgers')->insert([
                ['customer_ledger_id'=>$sourceId,'type'=>$series,
                    'type_id'=>$sourceId,'voucher_number'=>$sourceId,
                    'project_head_subheads_id'=>$sourceId*10+1,
                    'amount_in'=>$incoming?125:0,'amount_out'=>$incoming?0:125,'is_active'=>0],
                ['customer_ledger_id'=>$sourceId,'type'=>$series,
                    'type_id'=>$sourceId,'voucher_number'=>$sourceId,
                    'project_head_subheads_id'=>$sourceId*10+2,
                    'amount_in'=>$incoming?0:125,'amount_out'=>$incoming?125:0,'is_active'=>0],
            ]);
            $this->seedPreviouslySubmittedApproval($series,'customer_ledger',$sourceId);
            app(DailyVoucherApprovalWorkflow::class)->decide(
                DailyVoucherApproval::query()->where('source_id',$sourceId)->firstOrFail(),
                20,'approved'
            );
            $this->assertSame(1,(int)DB::table('customer_ledger')
                ->where('id',$sourceId)->value('is_active'),$series);
            $this->assertSame(2,DB::table('ledgers')
                ->where('customer_ledger_id',$sourceId)->where('is_active',1)->count(),$series);
            $this->assertSame(125.0,(float)DB::table('ledgers')
                ->where('customer_ledger_id',$sourceId)->sum('amount_in'),$series);
            $this->assertSame(125.0,(float)DB::table('ledgers')
                ->where('customer_ledger_id',$sourceId)->sum('amount_out'),$series);
            $this->assertSame('approved',(string)DB::table('daily_voucher_approvals')
                ->where('source_id',$sourceId)->value('status'),$series);
        }
    }

    public function test_cash_only_unbalanced_counterpart_blocks_approval_without_posting(): void
    {
        DB::table('customer_ledger')->insert([
            'id'=>73,'project_id'=>1,'transaction_type'=>'CCR','type_id'=>73,
            'payment_type'=>1,'amount_in'=>125,'amount_out'=>0,'is_active'=>0,
        ]);
        DB::table('project_head_subheads')->insert([
            ['id'=>731,'project_id'=>1],['id'=>732,'project_id'=>1],
        ]);
        DB::table('ledgers')->insert([
            ['customer_ledger_id'=>73,'type'=>'CCR','type_id'=>73,'voucher_number'=>73,
                'project_head_subheads_id'=>731,'amount_in'=>125,'amount_out'=>0,'is_active'=>0],
            ['customer_ledger_id'=>73,'type'=>'CCR','type_id'=>73,'voucher_number'=>73,
                'project_head_subheads_id'=>732,'amount_in'=>0,'amount_out'=>100,'is_active'=>0],
        ]);
        $this->seedPreviouslySubmittedApproval('CCR','customer_ledger',73);
        try {
            app(DailyVoucherApprovalWorkflow::class)->decide(
                DailyVoucherApproval::query()->where('source_id',73)->firstOrFail(),
                20,'approved'
            );
            $this->fail('Unbalanced cash-only ledger approved.');
        } catch (\DomainException $e) {
            $this->assertSame('STANDARD_VOUCHER_UNBALANCED_POSTING',$e->getMessage());
        }
        $this->assertSame(0,(int)DB::table('customer_ledger')->where('id',73)->value('is_active'));
        $this->assertSame(0,DB::table('ledgers')->where('customer_ledger_id',73)
            ->where('is_active',1)->count());
        $this->assertSame('pending',DB::table('daily_voucher_approvals')
            ->where('source_id',73)->value('status'));
    }

    public function test_rejection_requires_a_reason_and_keeps_the_voucher_unposted(): void
    {
        DB::table('customer_ledger')->insert(['id' => 7, 'project_id' => 1, 'is_active' => 0]);
        DB::table('project_head_subheads')->insert(['id' => 11, 'project_id' => 1]);
        DB::table('ledgers')->insert(['id' => 31, 'customer_ledger_id' => 7, 'type' => 'CR', 'type_id' => 7, 'project_head_subheads_id' => 11, 'is_active' => 0]);
        $workflow = app(DailyVoucherApprovalWorkflow::class);
        $this->seedPreviouslySubmittedApproval('CR', 'customer_ledger', 7);
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
        $this->seedPreviouslySubmittedApproval('CP', 'ledgers', 41);
        $workflow->decide(DailyVoucherApproval::query()->firstOrFail(), 20, 'approved');
        $this->assertSame(1, (int) DB::table('ledgers')->where('id', 41)->value('is_active'));
        $this->assertSame(0, (int) DB::table('ledgers')->where('id', 42)->value('is_active'));
    }
    /** Seed an earlier pending request for testing approve/reject decisions. */
    private function seedPreviouslySubmittedApproval(string $type,string $sourceTable,int $sourceId): void
    {
        $id=DB::table('daily_voucher_approvals')->insertGetId([
            'project_id'=>1,'voucher_type'=>$type,'source_table'=>$sourceTable,
            'source_id'=>$sourceId,'status'=>'pending',
            'assigned_approver_id'=>20,'submitted_by'=>10,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('daily_voucher_approval_events')->insert([
            'approval_id'=>$id,'event'=>'submitted','actor_id'=>10,'created_at'=>now(),
        ]);
    }

}
