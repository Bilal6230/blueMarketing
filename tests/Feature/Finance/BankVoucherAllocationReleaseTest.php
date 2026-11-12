<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\BankVoucherAllocationRelease;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Isolated SQLite accounting fixtures: never mutate client data. */
final class BankVoucherAllocationReleaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('projects', fn(Blueprint $t) => $t->id());
        Schema::create('customer_ledger', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('transaction_type'); $t->integer('payment_type');
            $t->integer('is_active'); $t->decimal('amount_in',18,2);
            $t->decimal('amount_out',18,2); $t->string('delete_reason')->nullable();
            $t->timestamps();
        });
        Schema::create('project_voucher_display_numbers', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('source_kind'); $t->unsignedBigInteger('source_id');
            $t->string('series'); $t->string('display_number');
        });
        Schema::create('project_head_subheads', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
        });
        Schema::create('ledgers', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_ledger_id');
            $t->unsignedBigInteger('project_head_subheads_id');
            $t->string('type'); $t->integer('is_active');
            $t->decimal('amount_in',18,2); $t->decimal('amount_out',18,2);
            $t->string('delete_reason')->nullable(); $t->unsignedBigInteger('update_by')->nullable();
            $t->timestamps();
        });
        Schema::create('daily_voucher_approvals', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('source_table'); $t->unsignedBigInteger('source_id');
            $t->string('voucher_type'); $t->string('status');
        });
        Schema::create('daily_voucher_approval_events', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('approval_id');
            $t->string('event'); $t->unsignedBigInteger('actor_id');
            $t->text('remarks')->nullable(); $t->timestamp('created_at');
        });
        Schema::create('finance_online_intakes', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
        });
        Schema::create('finance_cheque_intakes', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
        });
        Schema::create('finance_bank_voucher_allocations', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('source_method'); $t->unsignedBigInteger('source_id');
            $t->string('voucher_type'); $t->unsignedBigInteger('voucher_id');
            $t->decimal('amount',18,2);
        });
        (require base_path('database/migrations/2026_11_12_000044_create_finance_bank_voucher_allocation_releases.php'))->up();
        DB::table('projects')->insert(['id'=>1]);
        DB::table('project_head_subheads')->insert(['id'=>3,'project_id'=>1]);
        DB::table('finance_online_intakes')->insert(['id'=>7,'project_id'=>1]);
        DB::table('finance_cheque_intakes')->insert(['id'=>8,'project_id'=>1]);
    }

    private function source(int $id,string $series,string $method,string $status,
        float $amount=500.00,float $allocation=500.00): void
    {
        $receipt=in_array($series,['CR','BOR'],true);
        $isActive=in_array($status,['approved','posted'],true)?1:0;
        $project=1;
        DB::table('customer_ledger')->insert([
            'id'=>$id,'project_id'=>$project,
            'transaction_type'=>$receipt?'CR':'CP',
            'payment_type'=>$method==='online'?2:3,
            'is_active'=>$isActive,
            'amount_in'=>$receipt?$amount:0,
            'amount_out'=>$receipt?0:$amount,
        ]);
        DB::table('project_voucher_display_numbers')->insert([
            'project_id'=>$project,'source_kind'=>'customer_ledger',
            'source_id'=>$id,'series'=>$series,'display_number'=>$series.'-'.$id,
        ]);
        DB::table('finance_bank_voucher_allocations')->insert([
            'id'=>$id,'project_id'=>$project,'source_method'=>$method,
            'source_id'=>$method==='online'?7:8,'voucher_type'=>$series,
            'voucher_id'=>$id,'amount'=>$allocation,
        ]);
        DB::table('ledgers')->insert([
            'customer_ledger_id'=>$id,'project_head_subheads_id'=>3,
            'type'=>$receipt?'CR':'CP','is_active'=>$isActive,
            'amount_in'=>$receipt?$amount:0,
            'amount_out'=>$receipt?0:$amount,
        ]);
        if($status!=='posted'){
            DB::table('daily_voucher_approvals')->insert([
                'project_id'=>$project,'source_table'=>'customer_ledger',
                'source_id'=>$id,'voucher_type'=>$series,'status'=>$status,
            ]);
        }
    }

    public function test_rejected_cheque_release_is_audited_and_cannot_be_repeated(): void
    {
        $this->source(12,'BOR','cheque','rejected');
        $service=app(BankVoucherAllocationRelease::class);
        $released=$service->release(1,12,42,'Rejected cheque allocation released');
        $this->assertSame('rejected_release',$released['action']);
        $this->assertSame(1,DB::table('finance_bank_voucher_allocations')->count());
        $this->assertSame(1,DB::table('finance_bank_voucher_allocation_releases')->count());
        $this->assertSame(1,DB::table('daily_voucher_approval_events')
            ->where('event','rejected_release')->count());
        $this->assertSame(0,(int)DB::table('customer_ledger')->where('id',12)->value('is_active'));
        try {
            $service->release(1,12,42,'Second release must fail');
            $this->fail('Duplicate release was accepted');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('already released',$e->getMessage());
        }
        $this->assertSame(1,DB::table('finance_bank_voucher_allocation_releases')->count());
    }

    public function test_posted_online_payment_is_soft_voided_once(): void
    {
        $this->source(13,'BOP','online','posted',750.00,750.00);
        $released=app(BankVoucherAllocationRelease::class)
            ->release(1,13,42,'Administrative void after reconciliation');
        $this->assertSame('posted_void_release',$released['action']);
        $this->assertSame(0,(int)DB::table('customer_ledger')->where('id',13)->value('is_active'));
        $this->assertSame(0,(int)DB::table('ledgers')->where('customer_ledger_id',13)->value('is_active'));
        $this->assertSame(1,DB::table('finance_bank_voucher_allocations')->count());
        $this->assertSame(1,DB::table('finance_bank_voucher_allocation_releases')->count());
    }

    public function test_pending_approval_may_not_release_reserved_funds(): void
    {
        $this->source(14,'BOR','online','pending');
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Pending approval');
        app(BankVoucherAllocationRelease::class)->release(1,14,42,'Premature release');
    }

    public function test_mismatched_allocation_blocks_release_without_mutation(): void
    {
        $this->source(15,'BOR','cheque','approved',500.00,400.00);
        try {
            app(BankVoucherAllocationRelease::class)->release(1,15,42,'Allocation mismatch');
            $this->fail('Mismatched allocation was accepted');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('allocation or amount',$e->getMessage());
        }
        $this->assertSame(1,(int)DB::table('customer_ledger')->where('id',15)->value('is_active'));
        $this->assertSame(0,DB::table('finance_bank_voucher_allocation_releases')->count());
    }

    public function test_bank_funded_dr_rejected_release_is_supported(): void
    {
        $this->source(16,'DR','online','rejected',300.00,300.00);
        $out=app(BankVoucherAllocationRelease::class)
            ->release(1,16,42,'Rejected DR source release');
        $this->assertSame('rejected_release',$out['action']);
        $this->assertSame('DR-16',$out['voucher_number']);
    }
}
