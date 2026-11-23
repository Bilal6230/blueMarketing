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
        self::assertSame('sqlite', DB::getDefaultConnection());
        self::assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        Schema::create('projects', fn(Blueprint $t) => $t->id());
        Schema::create('customer_ledger', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('transaction_type'); $t->integer('payment_type');
            $t->integer('is_active'); $t->decimal('amount_in',18,2);
            $t->decimal('amount_out',18,2); $t->string('delete_reason')->nullable();
            $t->timestamps();
        });
        Schema::create('booking_vouchers', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('customer_ledger_id');
            $t->string('voucher_series'); $t->boolean('is_active')->default(true);
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
        Schema::create('project_bank_accounts', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('account_mapping_id'); $t->boolean('is_active')->default(true);
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
            $t->unsignedBigInteger('bank_account_id')->nullable();
            $t->decimal('amount',18,2);
        });
        // Isolated SQLite fixture: do not require retired MySQL-only migration files.
        // Production schema is managed by the current one-table baseline migration.
        Schema::create('finance_bank_voucher_allocation_releases', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('allocation_id')->unique();
            $t->string('source_method'); $t->unsignedBigInteger('source_id');
            $t->string('voucher_type'); $t->unsignedBigInteger('voucher_id');
            $t->decimal('amount',18,2); $t->string('action',24);
            $t->string('reason',500); $t->unsignedBigInteger('released_by');
            $t->timestamp('released_at'); $t->timestamps();
        });
        DB::table('projects')->insert(['id'=>1]);
        DB::table('project_head_subheads')->insert(['id'=>3,'project_id'=>1]);
        DB::table('project_head_subheads')->insert(['id'=>4,'project_id'=>1]);
        DB::table('project_bank_accounts')->insert([
            'id'=>1,'project_id'=>1,'account_mapping_id'=>4,'is_active'=>1,
        ]);
        DB::table('finance_online_intakes')->insert(['id'=>7,'project_id'=>1]);
        DB::table('finance_cheque_intakes')->insert(['id'=>8,'project_id'=>1]);
    }

    private function source(int $id,string $series,string $method,string $status,
        float $amount=500.00,float $allocation=500.00): void
    {
        $receipt=in_array($series,['CR','BOR'],true);
        $ledgerReceipt=$receipt || $series==='PPR';
        $isActive=in_array($status,['approved','posted'],true)?1:0;
        $project=1;
        DB::table('customer_ledger')->insert([
            'id'=>$id,'project_id'=>$project,
            'transaction_type'=>$series==='PPR'?'PPR':($receipt?'CR':'CP'),
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
            'type'=>$series==='PPR'?'PPR':($receipt?'CR':'CP'),'is_active'=>$isActive,
            'amount_in'=>$ledgerReceipt?$amount:0,
            'amount_out'=>$ledgerReceipt?0:$amount,
        ]);
        if($series==='PPR'){
            DB::table('booking_vouchers')->insert([
                'project_id'=>$project,'customer_ledger_id'=>$id,
                'voucher_series'=>'PPR','is_active'=>1,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
        if($status!=='posted'){
            DB::table('daily_voucher_approvals')->insert([
                'project_id'=>$project,'source_table'=>'customer_ledger',
                'source_id'=>$id,'voucher_type'=>$series,'status'=>$status,
            ]);
        }
    }

    public function test_new_balanced_bor_releases_both_bank_and_party_legs(): void
    {
        $this->source(28,'BOR','online','posted',425.00,425.00);
        DB::table('finance_bank_voucher_allocations')->where('voucher_id',28)
            ->update(['bank_account_id'=>1]);
        DB::table('ledgers')->where('customer_ledger_id',28)->delete();
        DB::table('ledgers')->insert([
            ['customer_ledger_id'=>28,'project_head_subheads_id'=>3,
                'type'=>'BOR','is_active'=>1,'amount_in'=>0,'amount_out'=>425],
            ['customer_ledger_id'=>28,'project_head_subheads_id'=>4,
                'type'=>'BOR','is_active'=>1,'amount_in'=>425,'amount_out'=>0],
        ]);
        $release=app(BankVoucherAllocationRelease::class)
            ->release(1,28,42,'Verified balanced bank receipt replaced');
        $this->assertSame('posted_void_release',$release['action']);
        $this->assertSame(2,DB::table('ledgers')
            ->where('customer_ledger_id',28)->where('is_active',0)->count());
        $this->assertSame(0,DB::table('ledgers')
            ->where('customer_ledger_id',28)->where('is_active',1)->count());
        $this->assertSame(1,DB::table('finance_bank_voucher_allocation_releases')
            ->where('voucher_type','BOR')->count());
    }

    public function test_balanced_bop_rejects_wrong_bank_leg_direction_without_releasing(): void
    {
        $this->source(29,'BOP','cheque','posted',190.00,190.00);
        DB::table('finance_bank_voucher_allocations')->where('voucher_id',29)
            ->update(['bank_account_id'=>1]);
        DB::table('ledgers')->where('customer_ledger_id',29)->delete();
        DB::table('ledgers')->insert([
            ['customer_ledger_id'=>29,'project_head_subheads_id'=>3,
                'type'=>'BOP','is_active'=>1,'amount_in'=>190,'amount_out'=>0],
            ['customer_ledger_id'=>29,'project_head_subheads_id'=>4,
                'type'=>'BOP','is_active'=>1,'amount_in'=>190,'amount_out'=>0],
        ]);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('BANK_VOUCHER_DOUBLE_ENTRY_DIRECTION_INVALID');
        app(BankVoucherAllocationRelease::class)
            ->release(1,29,42,'Corrupt balanced payment cannot release');
    }

    public function test_posted_ppr_cheque_releases_once_and_retires_booking_voucher(): void
    {
        $this->source(16,'PPR','cheque','posted',325.00,325.00);
        $release=app(BankVoucherAllocationRelease::class)
            ->release(1,16,42,'Verified cheque PPR source replaced');
        $this->assertSame('posted_void_release',$release['action']);
        $this->assertSame(0,(int)DB::table('customer_ledger')->where('id',16)->value('is_active'));
        $this->assertSame(0,(int)DB::table('ledgers')->where('customer_ledger_id',16)->value('is_active'));
        $this->assertSame(0,(int)DB::table('booking_vouchers')->where('customer_ledger_id',16)->value('is_active'));
        $this->assertSame(1,DB::table('finance_bank_voucher_allocations')->where('voucher_type','PPR')->count());
        $this->assertSame(1,DB::table('finance_bank_voucher_allocation_releases')->where('voucher_type','PPR')->count());
        $this->expectException(\DomainException::class);
        app(BankVoucherAllocationRelease::class)->release(1,16,42,'Already released PPR');
    }

    public function test_pending_ppr_approval_cannot_release_without_cancellation(): void
    {
        $this->source(17,'PPR','online','pending',215.00,215.00);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Pending approval');
        app(BankVoucherAllocationRelease::class)->release(1,17,42,'Premature pending PPR');
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
