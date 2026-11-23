<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\BankLinkedCorrection;
use App\Services\Finance\BankLinkedCorrectionApproval;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Disposable in-memory ledger tests: no customer database access. */
final class BankLinkedCorrectionTest extends TestCase
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

    public function test_balanced_cr_correction_changes_both_ledger_sides_atomically(): void
    {
        DB::table('project_head_subheads')->insert(['id'=>8,'project_id'=>1]);
        $this->voucher(34,'CR','online',55,'500.00');
        DB::table('ledgers')->insert([
            'customer_ledger_id'=>34,'project_head_subheads_id'=>8,
            'type'=>'CR','is_active'=>1,'amount_in'=>0,'amount_out'=>500,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $service=app(BankLinkedCorrection::class);
        $preview=$service->preview(1,'online',55,'400.00',[34=>'400.00']);
        $service->confirm(1,'online',55,4,'Correct verified CR amount',
            '400.00',[34=>'400.00'],$preview['fingerprint']);
        $this->assertEquals(400,DB::table('ledgers')->where('customer_ledger_id',34)->sum('amount_in'));
        $this->assertEquals(400,DB::table('ledgers')->where('customer_ledger_id',34)->sum('amount_out'));
        $this->assertSame(2,DB::table('ledgers')->where('customer_ledger_id',34)->count());
    }

    public function test_balanced_dr_correction_changes_both_ledger_sides_atomically(): void
    {
        DB::table('project_head_subheads')->insert(['id'=>8,'project_id'=>1]);
        $this->voucher(35,'DR','cheque',77,'500.00');
        DB::table('ledgers')->insert([
            'customer_ledger_id'=>35,'project_head_subheads_id'=>8,
            'type'=>'DR','is_active'=>1,'amount_in'=>500,'amount_out'=>0,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $service=app(BankLinkedCorrection::class);
        $preview=$service->preview(1,'cheque',77,'400.00',[35=>'400.00']);
        $service->confirm(1,'cheque',77,4,'Correct verified DR amount',
            '400.00',[35=>'400.00'],$preview['fingerprint']);
        $this->assertEquals(400,DB::table('ledgers')->where('customer_ledger_id',35)->sum('amount_in'));
        $this->assertEquals(400,DB::table('ledgers')->where('customer_ledger_id',35)->sum('amount_out'));
        $this->assertSame(2,DB::table('ledgers')->where('customer_ledger_id',35)->count());
    }

    public function test_same_id_two_voucher_correction_updates_allocations_and_original_ledgers_atomically(): void
    {
        $this->voucher(31,'BOR','online',55,'300.00');
        $this->voucher(32,'BOP','online',55,'200.00');
        $service=app(BankLinkedCorrection::class);
        $preview=$service->preview(1,'online',55,'450.00',[31=>'250.00',32=>'200.00']);
        self::assertSame('500.00',$preview['amount_before']);
        self::assertSame('450.00',$preview['amount_after']);
        self::assertCount(2,$preview['vouchers']);
        $result=$service->confirm(1,'online',55,7,'Correct bank amount',
            '450.00',[31=>'250.00',32=>'200.00'],$preview['fingerprint']);
        self::assertSame(1,$result['updated_vouchers']);
        self::assertSame(1,DB::table('finance_online_intakes')->count());
        self::assertSame('450', (string)(float)DB::table('finance_online_intakes')->where('id',55)->value('amount'));
        self::assertSame(2,DB::table('customer_ledger')->count());
        self::assertSame(2,DB::table('ledgers')->count());
        self::assertSame(2,DB::table('finance_bank_voucher_allocations')->count());
        self::assertSame(0,DB::table('finance_bank_voucher_allocation_releases')->count());
        self::assertEquals(250.00,DB::table('customer_ledger')->where('id',31)->value('amount_in'));
        self::assertEquals(250.00,DB::table('ledgers')->where('customer_ledger_id',31)->value('amount_in'));
        self::assertEquals(200.00,DB::table('ledgers')->where('customer_ledger_id',32)->value('amount_out'));
        self::assertSame(2,DB::table('daily_voucher_edit_snapshots')->count());
        self::assertSame(1,DB::table('daily_voucher_edit_snapshots')->where('status','bank_source_fix')->count());
        self::assertSame('BOR-0031',DB::table('project_voucher_display_numbers')->where('source_id',31)->value('display_number'));
        self::assertSame('approved',DB::table('daily_voucher_approvals')->where('source_id',31)->value('status'));
    }

    public function test_stale_review_rejected_with_no_partial_ledger_change(): void
    {
        $this->voucher(31,'BOR','online',55,'500.00');
        $service=app(BankLinkedCorrection::class);
        $preview=$service->preview(1,'online',55,'450.00',[31=>'450.00']);
        DB::table('finance_online_intakes')->where('id',55)->update(['reference'=>'TX-CHANGED']);
        try {
            $service->confirm(1,'online',55,7,'Stale data correction',
                '450.00',[31=>'450.00'],$preview['fingerprint']);
            self::fail('Stale review was accepted');
        } catch (\DomainException $e) {
            self::assertStringContainsString('Review the correction again',$e->getMessage());
        }
        self::assertEquals(500.00,DB::table('ledgers')->where('customer_ledger_id',31)->value('amount_in'));
        self::assertEquals(500.00,DB::table('finance_online_intakes')->where('id',55)->value('amount'));
        self::assertSame(0,DB::table('daily_voucher_edit_snapshots')->count());
    }

    public function test_excess_or_missing_proposed_allocations_are_refused(): void
    {
        $this->voucher(31,'BOR','online',55,'300.00');
        $this->voucher(32,'BOP','online',55,'200.00');
        $service=app(BankLinkedCorrection::class);
        foreach ([[31=>'400.00',32=>'200.00'],[31=>'300.00']] as $proposal) {
            try {
                $service->preview(1,'online',55,'450.00',$proposal);
                self::fail('Invalid allocation preview was accepted');
            } catch (\DomainException $e) {
                self::assertNotEmpty($e->getMessage());
            }
        }
        self::assertEquals(500.00,DB::table('finance_online_intakes')->where('id',55)->value('amount'));
        self::assertSame(0,DB::table('daily_voucher_edit_snapshots')->count());
    }

    public function test_cheque_pending_approval_and_released_allocation_are_refused(): void
    {
        $this->voucher(41,'BOR','cheque',77,'500.00','pending');
        $service=app(BankLinkedCorrection::class);
        $this->expectException(\DomainException::class);
        $service->preview(1,'cheque',77,'450.00',[41=>'450.00']);
    }

    public function test_released_linked_voucher_is_not_silently_corrected(): void
    {
        $this->voucher(41,'BOR','cheque',77,'500.00');
        $allocationId=(int)DB::table('finance_bank_voucher_allocations')->where('voucher_id',41)->value('id');
        DB::table('finance_bank_voucher_allocation_releases')->insert(['allocation_id'=>$allocationId]);
        $preview=app(BankLinkedCorrection::class)->preview(1,'cheque',77,'450.00',[]);
        self::assertSame(1,$preview['historical_released_count']);
        self::assertSame([],$preview['vouchers']);
        self::assertSame('0.00',$preview['allocated_after']);
        try {
            app(BankLinkedCorrection::class)->preview(1,'cheque',77,'450.00',[41=>'450.00']);
            self::fail('Released voucher should never be editable.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('Specify exactly one',$e->getMessage());
        }
    }

    public function test_unallocated_confirmed_online_source_corrects_without_creating_vouchers(): void
    {
        $service=app(BankLinkedCorrection::class);
        $plan=$service->preview(1,'online',55,'420.00',[]);
        self::assertSame('500.00',$plan['unallocated_before']);
        self::assertSame('420.00',$plan['unallocated_after']);
        self::assertSame('0.00',$plan['allocated_after']);
        $service->confirm(1,'online',55,7,'Amount entry typo','420.00',[],$plan['fingerprint']);
        self::assertEquals(420,DB::table('finance_online_intakes')->where('id',55)->value('amount'));
        self::assertSame(0,DB::table('finance_bank_voucher_allocations')->count());
        self::assertSame(0,DB::table('ledgers')->count());
    }

    public function test_unallocated_source_with_unreconciled_claim_is_refused(): void
    {
        DB::table('finance_online_voucher_claims')->insert([
            'project_id'=>1,'online_intake_id'=>55
        ]);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Unreconciled bank claim');
        app(BankLinkedCorrection::class)->preview(1,'online',55,'420.00',[]);
    }

    public function test_partially_allocated_cheque_retains_unallocated_balance_and_voucher_ids(): void
    {
        $this->voucher(41,'BOR','cheque',77,'200.00');
        $service=app(BankLinkedCorrection::class);
        $plan=$service->preview(1,'cheque',77,'450.00',[41=>'200.00']);
        self::assertSame('300.00',$plan['unallocated_before']);
        self::assertSame('250.00',$plan['unallocated_after']);
        $service->confirm(1,'cheque',77,7,'Correction of unallocated balance',
            '450.00',[41=>'200.00'],$plan['fingerprint']);
        self::assertEquals(200,DB::table('ledgers')->where('customer_ledger_id',41)->value('amount_in'));
        self::assertEquals(450,DB::table('finance_cheque_intakes')->where('id',77)->value('amount'));
        self::assertSame(1,DB::table('finance_bank_voucher_allocations')->count());
    }

    public function test_legacy_same_id_amount_approval_entrypoint_refuses_mutation(): void
    {
        // The product flow has switched to source correction + archived linked
        // vouchers. This old writer is not allowed to bypass that workflow.
        $this->voucher(41,'BOR','cheque',77,'200.00');
        $plan=app(BankLinkedCorrection::class)->preview(
            1,'cheque',77,'420.00',[41=>'200.00']);
        try {
            app(BankLinkedCorrectionApproval::class)->submit(
                1,'cheque',77,7,'Old direct amount edit must be refused',
                '420.00',[41=>'200.00'],$plan['fingerprint']);
            self::fail('Retired correction path should not mutate posted vouchers.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('audited voucher reissue',$exception->getMessage());
        }
        self::assertEquals(500,DB::table('finance_cheque_intakes')->where('id',77)->value('amount'));
        self::assertEquals(200,DB::table('ledgers')->where('customer_ledger_id',41)->value('amount_in'));
        self::assertSame(0,DB::table('finance_bank_correction_requests')->count());
    }

    public function test_legacy_entrypoint_cannot_bypass_existing_pending_reissue_request(): void
    {
        $this->voucher(41,'BOR','cheque',77,'200.00');
        DB::table('finance_bank_correction_requests')->insert([
            'project_id'=>1,'source_method'=>'cheque','source_id'=>77,
            'submitted_by'=>7,'assigned_approver_id'=>8,'status'=>'pending',
            'fingerprint'=>str_repeat('a',64),'proposed_amount'=>420,
            'proposed_vouchers_json'=>'{}','reason'=>'Earlier correction',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $plan=app(BankLinkedCorrection::class)->preview(
            1,'cheque',77,'430.00',[41=>'200.00']);
        try {
            app(BankLinkedCorrectionApproval::class)->submit(
                1,'cheque',77,7,'Old path must not bypass pending approval',
                '430.00',[41=>'200.00'],$plan['fingerprint']);
            self::fail('Legacy endpoint unexpectedly accepted a correction.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('audited voucher reissue',$exception->getMessage());
        }
        self::assertSame('pending',DB::table('finance_bank_correction_requests')
            ->where('source_method','cheque')->where('source_id',77)->value('status'));
        self::assertEquals(500,DB::table('finance_cheque_intakes')->where('id',77)->value('amount'));
        self::assertEquals(200,DB::table('ledgers')->where('customer_ledger_id',41)->value('amount_in'));
    }

    public function test_cross_project_cannot_read_or_correct_linked_cheque(): void
    {
        $this->voucher(41,'BOR','cheque',77,'500.00');
        $this->expectException(\DomainException::class);
        app(BankLinkedCorrection::class)->preview(2,'cheque',77,'450.00',[41=>'450.00']);
    }

    public function test_verified_cheque_same_id_correction_keeps_bank_reference_and_history(): void
    {
        $this->voucher(41,'BOR','cheque',77,'500.00');
        $service=app(BankLinkedCorrection::class);
        $preview=$service->preview(1,'cheque',77,'450.00',[41=>'450.00']);
        $service->confirm(1,'cheque',77,7,'Wrong amount typo',
            '450.00',[41=>'450.00'],$preview['fingerprint']);
        self::assertSame('CH77',DB::table('finance_cheque_intakes')->where('id',77)->value('cheque_number'));
        self::assertSame('cleared',DB::table('finance_cheque_intakes')->where('id',77)->value('status'));
        self::assertSame(1,DB::table('finance_cheque_intakes')->count());
        self::assertEquals(450.00,DB::table('ledgers')->where('customer_ledger_id',41)->value('amount_in'));
    }
}
