<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\BankLinkedCorrection;
use App\Services\Finance\BankLinkedCorrectionApproval;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Disposable in-memory ledger tests: no customer database access. */
final class BankLinkedCorrectionApprovalIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('sqlite', DB::getDefaultConnection());
        self::assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        Schema::create('projects', fn (Blueprint $t) => $t->id());
        Schema::create('project_head_subheads', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
        });
        Schema::create('finance_online_intakes', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->string('status');
            $t->decimal('amount',18,2); $t->string('reference');
            $t->date('transaction_date'); $t->unsignedBigInteger('account_mapping_id');
            $t->timestamps();
        });
        Schema::create('finance_cheque_intakes', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->string('status');
            $t->decimal('amount',18,2); $t->string('cheque_number');
            $t->string('bank_name'); $t->date('cheque_date');
            $t->unsignedBigInteger('account_mapping_id');
            $t->unsignedBigInteger('customer_ledger_id')->nullable(); $t->timestamps();
        });
        Schema::create('finance_bank_voucher_allocations', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('source_method'); $t->unsignedBigInteger('source_id');
            $t->string('voucher_type'); $t->unsignedBigInteger('voucher_id');
            $t->decimal('amount',18,2);
        });
        Schema::create('finance_bank_voucher_allocation_releases', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('allocation_id');
        });
        Schema::create('finance_online_voucher_claims', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('online_intake_id');
        });
        Schema::create('finance_cheque_voucher_claims', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('cheque_intake_id');
        });
        Schema::create('project_voucher_display_numbers', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('source_kind'); $t->unsignedBigInteger('source_id');
            $t->string('series'); $t->string('display_number');
        });
        Schema::create('customer_ledger', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('transaction_type'); $t->unsignedTinyInteger('payment_type');
            $t->unsignedTinyInteger('is_active');
            $t->decimal('amount_in',18,2); $t->decimal('amount_out',18,2);
            $t->timestamps();
        });
        Schema::create('ledgers', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_ledger_id');
            $t->unsignedBigInteger('project_head_subheads_id');
            $t->string('type'); $t->unsignedTinyInteger('is_active');
            $t->decimal('amount_in',18,2); $t->decimal('amount_out',18,2);
            $t->unsignedBigInteger('update_by')->nullable(); $t->timestamps();
        });
        Schema::create('daily_voucher_approvals', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('source_table'); $t->unsignedBigInteger('source_id');
            $t->string('voucher_type'); $t->string('status');
        });
        Schema::create('daily_voucher_approval_events', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('approval_id');
            $t->string('event'); $t->text('remarks')->nullable();
        });
        Schema::create('daily_voucher_edit_snapshots', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('source_id');
            $t->unsignedBigInteger('approval_id')->nullable();
            $t->string('status'); $t->longText('before_snapshot');
            $t->longText('proposed_snapshot'); $t->string('reason');
            $t->unsignedBigInteger('submitted_by');
            $t->unsignedBigInteger('decided_by')->nullable(); $t->timestamps();
        });
        Schema::create('daily_voucher_approval_policies', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('voucher_type'); $t->boolean('approval_required');
            $t->unsignedBigInteger('approver_user_id')->nullable(); $t->timestamps();
        });
        Schema::create('finance_bank_correction_requests', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('source_method'); $t->unsignedBigInteger('source_id');
            $t->unsignedBigInteger('submitted_by'); $t->unsignedBigInteger('assigned_approver_id')->nullable();
            $t->unsignedBigInteger('decided_by')->nullable(); $t->string('status');
            $t->string('fingerprint'); $t->decimal('proposed_amount',18,2);
            $t->longText('proposed_vouchers_json'); $t->string('reason');
            $t->string('decision_reason')->nullable(); $t->timestamp('decided_at')->nullable();
            $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable();
            $t->unsignedBigInteger('project_id')->nullable();
            $t->unsignedInteger('status_id')->default(1); $t->timestamps();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps();
        });
        Schema::create('permissions', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps();
        });
        Schema::create('model_has_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id'); $t->string('model_type');
            $t->unsignedBigInteger('model_id');
        });
        Schema::create('model_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id'); $t->string('model_type');
            $t->unsignedBigInteger('model_id');
        });
        Schema::create('role_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id');
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::table('projects')->insert([['id'=>1],['id'=>2]]);
        DB::table('project_head_subheads')->insert([['id'=>7,'project_id'=>1],['id'=>9,'project_id'=>2]]);
        DB::table('finance_online_intakes')->insert([
            'id'=>55,'project_id'=>1,'status'=>'confirmed','amount'=>'500.00',
            'reference'=>'TX55','transaction_date'=>'2026-11-22',
            'account_mapping_id'=>7,'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('finance_cheque_intakes')->insert([
            'id'=>77,'project_id'=>1,'status'=>'cleared','amount'=>'500.00',
            'cheque_number'=>'CH77','bank_name'=>'Bank A','cheque_date'=>'2026-11-22',
            'account_mapping_id'=>7,'created_at'=>now(),'updated_at'=>now(),
        ]);

        DB::table('users')->insert([
            ['id'=>7,'name'=>'Submitter','email'=>'submitter@example.invalid','project_id'=>1,'status_id'=>1],
            ['id'=>8,'name'=>'Approver','email'=>'approver@example.invalid','project_id'=>1,'status_id'=>1],
        ]);
        Permission::findOrCreate('voucher approver','web');
        User::find(8)->givePermissionTo('voucher approver');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::table('daily_voucher_approval_policies')->insert([
            'project_id'=>1,'voucher_type'=>'CHQ_CORR','approval_required'=>true,
            'approver_user_id'=>8,'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    private function voucher(int $id, string $series, string $method, int $sourceId,
        string $amount, string $approvalStatus='approved'): void
    {
        $incoming=in_array($series,['CR','BOR'],true);
        $type=match($series) {'BOR'=>'CR','BOP'=>'CP', default=>$series};
        DB::table('customer_ledger')->insert([
            'id'=>$id,'project_id'=>1,'transaction_type'=>$type,
            'payment_type'=>$method==='online'?2:3,'is_active'=>1,
            'amount_in'=>$incoming?$amount:0,'amount_out'=>$incoming?0:$amount,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('ledgers')->insert([
            'customer_ledger_id'=>$id,'project_head_subheads_id'=>7,
            'type'=>$type,'is_active'=>1,'amount_in'=>$incoming?$amount:0,
            'amount_out'=>$incoming?0:$amount,'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('finance_bank_voucher_allocations')->insert([
            'project_id'=>1,'source_method'=>$method,'source_id'=>$sourceId,
            'voucher_type'=>$series,'voucher_id'=>$id,'amount'=>$amount,
        ]);
        DB::table('project_voucher_display_numbers')->insert([
            'project_id'=>1,'source_kind'=>'customer_ledger','source_id'=>$id,
            'series'=>$series,'display_number'=>$series.'-'.str_pad((string)$id,4,'0',STR_PAD_LEFT),
        ]);
        DB::table('daily_voucher_approvals')->insert([
            'project_id'=>1,'source_table'=>'customer_ledger','source_id'=>$id,
            'voucher_type'=>$series,'status'=>$approvalStatus,
        ]);
    }

    public function test_approval_on_stages_without_mutating_ledgers_then_applies_same_id_on_approve(): void
    {
        $this->voucher(41,'BOR','cheque',77,'200.00');
        $service=app(BankLinkedCorrectionApproval::class);
        $plan=app(BankLinkedCorrection::class)->preview(1,'cheque',77,'420.00',[41=>'190.00']);
        $submitted=$service->submit(1,'cheque',77,7,'Verified amount correction',
            '420.00',[41=>'190.00'],$plan['fingerprint']);
        self::assertTrue($submitted['pending']);
        self::assertEquals(500,DB::table('finance_cheque_intakes')->where('id',77)->value('amount'));
        self::assertEquals(200,DB::table('ledgers')->where('customer_ledger_id',41)->value('amount_in'));
        self::assertSame('pending',DB::table('finance_bank_correction_requests')
            ->where('id',$submitted['request_id'])->value('status'));
        $decision=$service->decide(1,$submitted['request_id'],8,'approved');
        self::assertSame('approved',$decision['status']);
        self::assertEquals(420,DB::table('finance_cheque_intakes')->where('id',77)->value('amount'));
        self::assertEquals(190,DB::table('ledgers')->where('customer_ledger_id',41)->value('amount_in'));
        self::assertSame(1,DB::table('finance_cheque_intakes')->count());
        self::assertSame(1,DB::table('customer_ledger')->count());
        self::assertSame(1,DB::table('ledgers')->count());
        self::assertSame(2,DB::table('daily_voucher_edit_snapshots')
            ->where('submitted_by',7)->where('decided_by',8)->count());
        self::assertSame('BOR-0041',DB::table('project_voucher_display_numbers')
            ->where('source_id',41)->value('display_number'));
    }

    public function test_rejection_keeps_source_and_voucher_balances_unchanged(): void
    {
        $this->voucher(41,'BOR','cheque',77,'200.00');
        $service=app(BankLinkedCorrectionApproval::class);
        $plan=app(BankLinkedCorrection::class)->preview(1,'cheque',77,'420.00',[41=>'190.00']);
        $request=$service->submit(1,'cheque',77,7,'Incorrect source amount',
            '420.00',[41=>'190.00'],$plan['fingerprint']);
        $result=$service->decide(1,$request['request_id'],8,'rejected','Original evidence valid');
        self::assertSame('rejected',$result['status']);
        self::assertEquals(500,DB::table('finance_cheque_intakes')->where('id',77)->value('amount'));
        self::assertEquals(200,DB::table('ledgers')->where('customer_ledger_id',41)->value('amount_in'));
        self::assertSame(0,DB::table('daily_voucher_edit_snapshots')->count());
    }

    public function test_stale_approved_request_rolls_back_and_remains_pending(): void
    {
        $this->voucher(41,'BOR','cheque',77,'200.00');
        $service=app(BankLinkedCorrectionApproval::class);
        $plan=app(BankLinkedCorrection::class)->preview(1,'cheque',77,'420.00',[41=>'190.00']);
        $request=$service->submit(1,'cheque',77,7,'Incorrect source amount',
            '420.00',[41=>'190.00'],$plan['fingerprint']);
        DB::table('finance_cheque_intakes')->where('id',77)->update(['cheque_number'=>'CHANGED']);
        try {
            $service->decide(1,$request['request_id'],8,'approved');
            self::fail('Stale bank correction should never be approved.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('Review the correction again',$e->getMessage());
        }
        self::assertEquals(500,DB::table('finance_cheque_intakes')->where('id',77)->value('amount'));
        self::assertEquals(200,DB::table('ledgers')->where('customer_ledger_id',41)->value('amount_in'));
        self::assertSame('pending',DB::table('finance_bank_correction_requests')
            ->where('id',$request['request_id'])->value('status'));
    }

    public function test_non_assigned_staff_cannot_approve_correction(): void
    {
        $this->voucher(41,'BOR','cheque',77,'200.00');
        $service=app(BankLinkedCorrectionApproval::class);
        $plan=app(BankLinkedCorrection::class)->preview(1,'cheque',77,'420.00',[41=>'190.00']);
        $request=$service->submit(1,'cheque',77,7,'Correct source amount',
            '420.00',[41=>'190.00'],$plan['fingerprint']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('authorized independent approver');
        $service->decide(1,$request['request_id'],7,'approved');
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }
}
