<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\BankSourceReissueCorrection;
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
            $t->string('reference_key')->nullable();$t->text('remarks')->nullable();
            $t->string('proof_path')->nullable();$t->unsignedBigInteger('created_by')->nullable();
            $t->date('transaction_date'); $t->unsignedBigInteger('account_mapping_id');
            foreach(['sending_bank','sending_account','receiving_bank','receiving_account','sender_name','receiver_name','purpose','type'] as $f) $t->string($f)->nullable();
            $t->timestamps();
        });
        Schema::create('finance_cheque_intakes', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->string('status');
            $t->decimal('amount',18,2); $t->string('cheque_number');
            $t->string('bank_name'); $t->date('cheque_date');
            foreach(['cheque_title','received_date','branch','received_from','purpose','direction'] as $f) $t->string($f)->nullable();
            $t->unsignedBigInteger('account_mapping_id');
            $t->unsignedBigInteger('customer_ledger_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->string('attachment_path')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();$t->timestamps();
        });
        Schema::create('finance_bank_voucher_allocations', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('source_method'); $t->unsignedBigInteger('source_id');
            $t->string('voucher_type'); $t->unsignedBigInteger('voucher_id');
            $t->decimal('amount',18,2);
        });
        Schema::create('finance_bank_voucher_allocation_releases', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('allocation_id');
            $t->unsignedBigInteger('project_id');$t->string('source_method');
            $t->unsignedBigInteger('source_id');$t->string('voucher_type');
            $t->unsignedBigInteger('voucher_id');$t->decimal('amount',18,2);
            $t->string('action');$t->string('reason');$t->unsignedBigInteger('released_by');
            $t->timestamp('released_at');$t->timestamps();
        });
        Schema::create('finance_cheque_intake_actions',function (Blueprint $t) {
            $t->id();$t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('cheque_intake_id');$t->string('action');
            $t->string('from_status');$t->string('to_status');
            $t->date('action_date');$t->string('bank_name');
            $t->decimal('original_amount',18,2);$t->unsignedBigInteger('actor_id');
            $t->string('reason')->nullable();$t->text('remarks')->nullable();
            $t->timestamp('verification_confirmed_at')->nullable();
            $t->timestamp('created_at')->nullable();
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
            $t->text('delete_reason')->nullable();$t->timestamps();
        });
        Schema::create('ledgers', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_ledger_id');
            $t->unsignedBigInteger('project_head_subheads_id');
            $t->string('type'); $t->unsignedTinyInteger('is_active');
            $t->decimal('amount_in',18,2); $t->decimal('amount_out',18,2);
            $t->unsignedBigInteger('update_by')->nullable(); $t->text('delete_reason')->nullable();$t->timestamps();
        });
        Schema::create('daily_voucher_approvals', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('source_table'); $t->unsignedBigInteger('source_id');
            $t->string('voucher_type'); $t->string('status');
        });
        Schema::create('daily_voucher_approval_events', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('approval_id');
            $t->string('event'); $t->text('remarks')->nullable();
            $t->unsignedBigInteger('actor_id');$t->timestamp('created_at')->nullable();
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
            'reference'=>'TX55','reference_key'=>'TX55','transaction_date'=>'2026-11-22',
            'account_mapping_id'=>7,'type'=>'receipt','sending_bank'=>'Bank A',
            'sending_account'=>'A1','receiving_bank'=>'Bank B','receiving_account'=>'B1',
            'sender_name'=>'Sender','receiver_name'=>'Office','purpose'=>'Old note',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('finance_cheque_intakes')->insert([
            'id'=>77,'project_id'=>1,'status'=>'cleared','amount'=>'500.00',
            'cheque_number'=>'CH77','bank_name'=>'Bank A','cheque_date'=>'2026-11-22',
            'account_mapping_id'=>7,'cheque_title'=>'Test Cheque','received_date'=>'2026-11-22',
            'branch'=>'Main','received_from'=>'Payer','purpose'=>'Old purpose','direction'=>'in',
            'created_at'=>now(),'updated_at'=>now(),
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
            'payment_type'=>$method==='online'?2:3,
            'is_active'=>in_array($approvalStatus,['pending','rejected'],true)?0:1,
            'amount_in'=>$incoming?$amount:0,'amount_out'=>$incoming?0:$amount,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('ledgers')->insert([
            'customer_ledger_id'=>$id,'project_head_subheads_id'=>7,
            'type'=>$type,'is_active'=>in_array($approvalStatus,['pending','rejected'],true)?0:1,'amount_in'=>$incoming?$amount:0,
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

    private function correctedCheque(string $amount='420.00',bool $linked=true): array
    {
        return ['cheque_number'=>$linked?'CH78':'CH77','bank_name'=>'Bank A','direction'=>'in',
            'cheque_title'=>'Corrected Test Cheque','cheque_date'=>'2026-11-22',
            'received_date'=>'2026-11-22','amount'=>$amount,'branch'=>'Main',
            'received_from'=>'Payer','purpose'=>'Corrected detail','account_mapping_id'=>7];
    }

    public function test_approval_on_preserves_postings_until_approved_then_soft_voids(): void
    {
        $this->voucher(41,'BOR','cheque',77,'200.00');
        $changes=$this->correctedCheque();
        $plan=app(BankSourceReissueCorrection::class)->preview(1,'cheque',77,$changes);
        $service=app(BankLinkedCorrectionApproval::class);
        $submitted=$service->submitReissue(1,'cheque',77,7,'Corrected after bank evidence review',
            $changes,$plan['fingerprint']);
        self::assertTrue($submitted['pending']);
        self::assertEquals(500,DB::table('finance_cheque_intakes')->where('id',77)->value('amount'));
        self::assertEquals(1,DB::table('ledgers')->where('customer_ledger_id',41)->value('is_active'));
        self::assertSame(0,DB::table('finance_bank_voucher_allocation_releases')->count());
        $decision=$service->decide(1,$submitted['request_id'],8,'approved');
        self::assertSame('approved',$decision['status']);
        self::assertSame('withdrawn',DB::table('finance_cheque_intakes')->where('id',77)->value('status'));
        self::assertEquals(420,DB::table('finance_cheque_intakes')->where('cheque_number','CH78')->value('amount'));
        self::assertSame('received',DB::table('finance_cheque_intakes')->where('cheque_number','CH78')->value('status'));
        self::assertEquals(0,DB::table('ledgers')->where('customer_ledger_id',41)->value('is_active'));
        self::assertEquals(0,DB::table('customer_ledger')->where('id',41)->value('is_active'));
        self::assertSame(1,DB::table('finance_bank_voucher_allocation_releases')->count());
        self::assertSame('BOR-0041',DB::table('project_voucher_display_numbers')
            ->where('source_id',41)->value('display_number'));
        self::assertSame(2,DB::table('finance_cheque_intakes')->count());
        self::assertSame(1,DB::table('daily_voucher_edit_snapshots')
            ->where('submitted_by',7)->where('decided_by',8)->count());
    }

    public function test_rejection_keeps_original_financial_effect_unchanged(): void
    {
        $this->voucher(41,'BOR','cheque',77,'200.00');
        $changes=$this->correctedCheque();
        $plan=app(BankSourceReissueCorrection::class)->preview(1,'cheque',77,$changes);
        $service=app(BankLinkedCorrectionApproval::class);
        $request=$service->submitReissue(1,'cheque',77,7,'Incorrect amount',
            $changes,$plan['fingerprint']);
        $decision=$service->decide(1,$request['request_id'],8,'rejected','Original amount verified');
        self::assertSame('rejected',$decision['status']);
        self::assertEquals(500,DB::table('finance_cheque_intakes')->where('id',77)->value('amount'));
        self::assertEquals(1,DB::table('ledgers')->where('customer_ledger_id',41)->value('is_active'));
        self::assertSame(0,DB::table('finance_bank_voucher_allocation_releases')->count());
    }

    public function test_stale_approved_request_refuses_all_changes(): void
    {
        $this->voucher(41,'BOR','cheque',77,'200.00');
        $changes=$this->correctedCheque();
        $plan=app(BankSourceReissueCorrection::class)->preview(1,'cheque',77,$changes);
        $service=app(BankLinkedCorrectionApproval::class);
        $request=$service->submitReissue(1,'cheque',77,7,'Source correction',
            $changes,$plan['fingerprint']);
        DB::table('finance_cheque_intakes')->where('id',77)->update(['bank_name'=>'Changed Bank']);
        try {
            $service->decide(1,$request['request_id'],8,'approved');
            self::fail('Stale request must be rejected.');
        } catch (\DomainException $e) {
            self::assertNotEmpty($e->getMessage());
        }
        self::assertSame('pending',DB::table('finance_bank_correction_requests')
            ->where('id',$request['request_id'])->value('status'));
        self::assertSame(0,DB::table('finance_bank_voucher_allocation_releases')->count());
    }

    public function test_non_assigned_staff_cannot_approve_reissue(): void
    {
        $this->voucher(41,'BOR','cheque',77,'200.00');
        $changes=$this->correctedCheque();
        $plan=app(BankSourceReissueCorrection::class)->preview(1,'cheque',77,$changes);
        $request=app(BankLinkedCorrectionApproval::class)->submitReissue(
            1,'cheque',77,7,'Correction requested',$changes,$plan['fingerprint']);
        $this->expectException(\DomainException::class);
        app(BankLinkedCorrectionApproval::class)->decide(1,$request['request_id'],7,'approved');
    }

    public function test_approval_off_releases_partial_allocation_and_creates_new_unverified_source(): void
    {
        DB::table('daily_voucher_approval_policies')
            ->where('project_id',1)->where('voucher_type','CHQ_CORR')
            ->update(['approval_required'=>false]);
        $this->voucher(41,'BOR','cheque',77,'200.00');
        $changes=$this->correctedCheque('470.00');
        $plan=app(BankSourceReissueCorrection::class)->preview(1,'cheque',77,$changes);
        $result=app(BankLinkedCorrectionApproval::class)->submitReissue(
            1,'cheque',77,7,'Correct partially allocated source',
            $changes,$plan['fingerprint']);
        self::assertFalse($result['pending']);
        self::assertEquals(470,DB::table('finance_cheque_intakes')->where('cheque_number','CH78')->value('amount'));
        self::assertSame('withdrawn',DB::table('finance_cheque_intakes')->where('id',77)->value('status'));
        self::assertSame(1,DB::table('finance_bank_voucher_allocation_releases')->count());
        self::assertSame(0,DB::table('ledgers')->where('customer_ledger_id',41)->value('is_active'));
    }

    public function test_cleared_cheque_without_vouchers_remains_same_id_and_ready_for_first_voucher(): void
    {
        DB::table('daily_voucher_approval_policies')
            ->where('project_id',1)->where('voucher_type','CHQ_CORR')
            ->update(['approval_required'=>false]);
        $changes=$this->correctedCheque('460.00',false);
        $plan=app(BankSourceReissueCorrection::class)->preview(1,'cheque',77,$changes);
        self::assertCount(0,$plan['active_vouchers']);
        $result=app(BankLinkedCorrectionApproval::class)->submitReissue(
            1,'cheque',77,7,'Typo in amount before creating voucher',
            $changes,$plan['fingerprint']);
        self::assertFalse($result['pending']);
        self::assertEquals(460,DB::table('finance_cheque_intakes')->where('id',77)->value('amount'));
        self::assertSame('cleared',DB::table('finance_cheque_intakes')->where('id',77)->value('status'));
        self::assertSame(1,DB::table('finance_cheque_intakes')->count());
        self::assertSame(0,DB::table('customer_ledger')->count());
        self::assertSame(0,DB::table('finance_bank_voucher_allocation_releases')->count());
    }

    public function test_two_fully_allocated_vouchers_are_both_released_atomically(): void
    {
        DB::table('daily_voucher_approval_policies')
            ->where('project_id',1)->where('voucher_type','CHQ_CORR')
            ->update(['approval_required'=>false]);
        $this->voucher(41,'BOR','cheque',77,'300.00');
        $this->voucher(42,'BOR','cheque',77,'200.00');
        $changes=$this->correctedCheque('480.00');
        $plan=app(BankSourceReissueCorrection::class)->preview(1,'cheque',77,$changes);
        self::assertCount(2,$plan['active_vouchers']);
        app(BankLinkedCorrectionApproval::class)->submitReissue(
            1,'cheque',77,7,'Reallocate all vouchers afresh',$changes,$plan['fingerprint']);
        self::assertSame(2,DB::table('finance_bank_voucher_allocation_releases')->count());
        self::assertSame(0,DB::table('ledgers')->where('is_active',1)->count());
        self::assertSame(2,DB::table('finance_bank_voucher_allocations')->count());
        self::assertSame(2,DB::table('project_voucher_display_numbers')->count());
        self::assertSame(2,DB::table('finance_cheque_intakes')->count());
    }

    public function test_pending_unposted_voucher_is_cancelled_and_released_before_reissue(): void
    {
        DB::table('daily_voucher_approval_policies')
            ->where('project_id',1)->where('voucher_type','CHQ_CORR')
            ->update(['approval_required'=>false]);
        $this->voucher(41,'BOR','cheque',77,'200.00','pending');
        $changes=$this->correctedCheque();
        $plan=app(BankSourceReissueCorrection::class)->preview(1,'cheque',77,$changes);
        self::assertSame('pending',$plan['active_vouchers'][0]['approval_status']);
        $result=app(BankLinkedCorrectionApproval::class)->submitReissue(
            1,'cheque',77,7,'Correct source with pending voucher',
            $changes,$plan['fingerprint']);
        self::assertFalse($result['pending']);
        self::assertEquals(420,DB::table('finance_cheque_intakes')->where('cheque_number','CH78')->value('amount'));
        self::assertSame('withdrawn',DB::table('finance_cheque_intakes')->where('id',77)->value('status'));
        self::assertSame('rejected',DB::table('daily_voucher_approvals')
            ->where('source_id',41)->value('status'));
        self::assertSame(0,DB::table('ledgers')->where('customer_ledger_id',41)->value('is_active'));
        self::assertSame('pending_cancel_release',
            DB::table('finance_bank_voucher_allocation_releases')->where('voucher_id',41)->value('action'));
        self::assertSame(2,DB::table('finance_cheque_intakes')->count());
    }

    public function test_pending_voucher_with_active_posting_refuses_atomic_cancellation(): void
    {
        $this->voucher(41,'BOR','cheque',77,'200.00','pending');
        DB::table('ledgers')->where('customer_ledger_id',41)->update(['is_active'=>1]);
        $this->expectException(\DomainException::class);
        app(BankSourceReissueCorrection::class)->preview(1,'cheque',77,$this->correctedCheque());
    }

    public function test_withdrawn_unlinked_cheque_edits_original_id_without_restoration(): void
    {
        DB::table('daily_voucher_approval_policies')
            ->where('project_id',1)->where('voucher_type','CHQ_CORR')
            ->update(['approval_required'=>false]);
        DB::table('finance_cheque_intakes')->where('id',77)->update(['status'=>'withdrawn']);
        DB::table('finance_cheque_intakes')->insert([
            'id'=>78,'project_id'=>1,'status'=>'withdrawn','amount'=>'500.00',
            'cheque_number'=>'CH78','bank_name'=>'Bank A',
            'cheque_date'=>'2026-11-22','account_mapping_id'=>7,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('finance_cheque_intake_actions')->insert([
            'project_id'=>1,'cheque_intake_id'=>77,'action'=>'cleared',
            'from_status'=>'pending_clearing','to_status'=>'cleared',
            'action_date'=>'2026-11-22','bank_name'=>'Bank A',
            'original_amount'=>'500.00','actor_id'=>7,
            'verification_confirmed_at'=>now(),'created_at'=>now(),
        ]);
        DB::table('finance_cheque_intake_actions')->insert([
            'project_id'=>1,'cheque_intake_id'=>77,'action'=>'withdrawn',
            'from_status'=>'cleared','to_status'=>'withdrawn',
            'action_date'=>'2026-11-22','bank_name'=>'Bank A',
            'original_amount'=>'500.00','actor_id'=>7,
            'remarks'=>'Replaced by cheque source #78','created_at'=>now(),
        ]);
        $changes=$this->correctedCheque('490.00',false);
        $plan=app(BankSourceReissueCorrection::class)->preview(1,'cheque',77,$changes);
        self::assertFalse($plan['requires_replacement']);
        $result=app(BankLinkedCorrectionApproval::class)->submitReissue(
            1,'cheque',77,7,'Restore previously replaced cleared cheque',
            $changes,$plan['fingerprint']);
        self::assertFalse($result['pending']);
        self::assertSame('withdrawn',DB::table('finance_cheque_intakes')
            ->where('id',77)->value('status'));
        self::assertSame(2,DB::table('finance_cheque_intakes')->count());
        self::assertSame(0,DB::table('finance_cheque_intake_actions')
            ->where('action','restored')->count());
    }

    public function test_genuinely_withdrawn_cleared_cheque_stays_withdrawn_after_edit(): void
    {
        DB::table('daily_voucher_approval_policies')
            ->where('project_id',1)->where('voucher_type','CHQ_CORR')
            ->update(['approval_required'=>false]);
        DB::table('finance_cheque_intakes')->where('id',77)->update(['status'=>'withdrawn']);
        DB::table('finance_cheque_intake_actions')->insert([
            'project_id'=>1,'cheque_intake_id'=>77,'action'=>'cleared',
            'from_status'=>'pending_clearing','to_status'=>'cleared',
            'action_date'=>'2026-11-22','bank_name'=>'Bank A',
            'original_amount'=>'500.00','actor_id'=>7,
            'verification_confirmed_at'=>now(),'created_at'=>now(),
        ]);
        $changes=$this->correctedCheque('490.00',false);
        $plan=app(BankSourceReissueCorrection::class)->preview(1,'cheque',77,$changes);
        self::assertFalse($plan['ready_after_correction']);
        app(BankLinkedCorrectionApproval::class)->submitReissue(
            1,'cheque',77,7,'Update withdrawn record without renewing funds',
            $changes,$plan['fingerprint']);
        self::assertSame('withdrawn',DB::table('finance_cheque_intakes')->where('id',77)->value('status'));
    }

    public function test_withdrawn_unverified_cheque_edit_does_not_fabricate_clearance(): void
    {
        DB::table('daily_voucher_approval_policies')
            ->where('project_id',1)->where('voucher_type','CHQ_CORR')
            ->update(['approval_required'=>false]);
        DB::table('finance_cheque_intakes')->where('id',77)->update(['status'=>'withdrawn']);
        $changes=$this->correctedCheque('490.00');
        $plan=app(BankSourceReissueCorrection::class)->preview(1,'cheque',77,$changes);
        self::assertFalse($plan['ready_after_correction']);
        app(BankLinkedCorrectionApproval::class)->submitReissue(
            1,'cheque',77,7,'Fix amount without bank evidence',
            $changes,$plan['fingerprint']);
        self::assertSame('withdrawn',DB::table('finance_cheque_intakes')
            ->where('id',77)->value('status'));
        self::assertSame(0,DB::table('finance_cheque_intake_actions')->count());
    }

    public function test_confirmed_online_pending_voucher_is_cancelled_and_source_stays_available(): void
    {
        $this->voucher(51,'BOR','online',55,'200.00','pending');
        $changes=[
            'reference'=>'TX56','type'=>'receipt','transaction_date'=>'2026-11-22',
            'amount'=>'475.00','sending_bank'=>'Bank A','sending_account'=>'A1',
            'receiving_bank'=>'Bank B','receiving_account'=>'B1','sender_name'=>'Sender',
            'receiver_name'=>'Office','purpose'=>'Corrected bank narration',
            'account_mapping_id'=>7,
        ];
        $plan=app(BankSourceReissueCorrection::class)->preview(1,'online',55,$changes);
        self::assertTrue($plan['requires_replacement']);
        $result=app(BankLinkedCorrectionApproval::class)->submitReissue(
            1,'online',55,7,'Fix linked online source before posting',
            $changes,$plan['fingerprint']);
        self::assertFalse($result['pending']);
        self::assertSame('withdrawn',DB::table('finance_online_intakes')->where('id',55)->value('status'));
        self::assertSame('pending',DB::table('finance_online_intakes')->where('reference','TX56')->value('status'));
        self::assertEquals(475,DB::table('finance_online_intakes')->where('reference','TX56')->value('amount'));
        self::assertSame('rejected',DB::table('daily_voucher_approvals')
            ->where('source_id',51)->value('status'));
        self::assertSame('pending_cancel_release',
            DB::table('finance_bank_voucher_allocation_releases')->where('voucher_id',51)->value('action'));
        self::assertSame(2,DB::table('finance_online_intakes')->count());
    }

    public function test_unverified_unlinked_cheque_can_edit_but_is_not_falsely_cleared(): void
    {
        DB::table('daily_voucher_approval_policies')
            ->where('project_id',1)->where('voucher_type','CHQ_CORR')
            ->update(['approval_required'=>false]);
        DB::table('finance_cheque_intakes')->where('id',77)->update(['status'=>'received']);
        $changes=$this->correctedCheque('490.00');
        $plan=app(BankSourceReissueCorrection::class)->preview(1,'cheque',77,$changes);
        self::assertFalse($plan['ready_after_correction']);
        app(BankLinkedCorrectionApproval::class)->submitReissue(
            1,'cheque',77,7,'Edit cheque before bank clearing',
            $changes,$plan['fingerprint']);
        self::assertSame('received',DB::table('finance_cheque_intakes')->where('id',77)->value('status'));
        self::assertEquals(490,DB::table('finance_cheque_intakes')->where('id',77)->value('amount'));
        self::assertSame(0,DB::table('finance_bank_voucher_allocation_releases')->count());
    }

    public function test_linked_cheque_requires_new_unique_reference_without_mutating_original(): void
    {
        $this->voucher(41,'BOR','cheque',77,'200.00');
        $changes=$this->correctedCheque('430.00',false);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('NEW unique cheque');
        try {
            app(BankSourceReissueCorrection::class)->preview(1,'cheque',77,$changes);
        } finally {
            self::assertSame(1,DB::table('finance_cheque_intakes')->count());
            self::assertEquals(1,DB::table('ledgers')->where('customer_ledger_id',41)->value('is_active'));
            self::assertSame(0,DB::table('finance_bank_voucher_allocation_releases')->count());
        }
    }

    public function test_historical_released_voucher_still_requires_new_bank_source_id(): void
    {
        DB::table('daily_voucher_approval_policies')->where('project_id',1)
            ->where('voucher_type','CHQ_CORR')->update(['approval_required'=>false]);
        $this->voucher(41,'BOR','cheque',77,'200.00');
        DB::table('customer_ledger')->where('id',41)->update(['is_active'=>0]);
        DB::table('ledgers')->where('customer_ledger_id',41)->update(['is_active'=>0]);
        DB::table('finance_bank_voucher_allocation_releases')->insert([
            'project_id'=>1,'allocation_id'=>1,'source_method'=>'cheque','source_id'=>77,
            'voucher_type'=>'BOR','voucher_id'=>41,'amount'=>'200.00',
            'action'=>'posted_void_release','reason'=>'Earlier audited void','released_by'=>7,
            'released_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        $changes=$this->correctedCheque('430.00');
        $plan=app(BankSourceReissueCorrection::class)->preview(1,'cheque',77,$changes);
        self::assertTrue($plan['requires_replacement']);
        self::assertCount(0,$plan['active_vouchers']);
        $result=app(BankLinkedCorrectionApproval::class)->submitReissue(
            1,'cheque',77,7,'Correct already released voucher source',
            $changes,$plan['fingerprint']);
        self::assertFalse($result['pending']);
        self::assertSame('withdrawn',DB::table('finance_cheque_intakes')->where('id',77)->value('status'));
        self::assertSame('received',DB::table('finance_cheque_intakes')->where('cheque_number','CH78')->value('status'));
        self::assertSame(2,DB::table('finance_cheque_intakes')->count());
        self::assertSame(1,DB::table('finance_bank_voucher_allocation_releases')->count());
    }

    public function test_confirmed_online_without_vouchers_updates_original_id_not_replacement(): void
    {
        $changes=[
            'reference'=>'TX55','type'=>'receipt','transaction_date'=>'2026-11-22',
            'amount'=>'430.00','sending_bank'=>'Bank A','sending_account'=>'A1',
            'receiving_bank'=>'Bank B','receiving_account'=>'B1',
            'sender_name'=>'Sender','receiver_name'=>'Office',
            'purpose'=>'Original unlinked source update','account_mapping_id'=>7,
        ];
        $plan=app(BankSourceReissueCorrection::class)->preview(1,'online',55,$changes);
        self::assertFalse($plan['requires_replacement']);
        $result=app(BankLinkedCorrectionApproval::class)->submitReissue(
            1,'online',55,7,'Correct original online source before vouchers',
            $changes,$plan['fingerprint']);
        self::assertFalse($result['pending']);
        self::assertSame(1,DB::table('finance_online_intakes')->count());
        self::assertSame('confirmed',DB::table('finance_online_intakes')->where('id',55)->value('status'));
        self::assertEquals(430,DB::table('finance_online_intakes')->where('id',55)->value('amount'));
        self::assertSame(0,DB::table('finance_bank_voucher_allocation_releases')->count());
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }
}
