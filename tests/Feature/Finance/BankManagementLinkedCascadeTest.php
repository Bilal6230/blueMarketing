<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\BankManagementSourceMutation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class BankManagementLinkedCascadeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('sqlite', DB::getDefaultConnection());
        self::assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        Schema::create('projects', fn(Blueprint $t) => $t->id());
        Schema::create('project_head_subheads', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
        });
        Schema::create('finance_online_intakes', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('status'); $t->decimal('amount',18,2);
            $t->string('reference')->nullable(); $t->string('reference_key')->nullable();
            $t->string('transaction_date')->nullable(); $t->string('type')->nullable();
            foreach (['sending_bank','sending_account','receiving_bank','receiving_account',
                'sender_name','receiver_name','purpose'] as $field) $t->string($field)->nullable();
            $t->unsignedBigInteger('account_mapping_id')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->string('proof_path')->nullable(); $t->text('remarks')->nullable(); $t->timestamps();
        });
        Schema::create('finance_cheque_intakes', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
        });
        Schema::create('customer_ledger', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->string('transaction_type');
            $t->integer('payment_type'); $t->integer('is_active');
            $t->decimal('amount_in',18,2); $t->decimal('amount_out',18,2);
            $t->string('delete_reason')->nullable(); $t->timestamps();
        });
        Schema::create('ledgers', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('customer_ledger_id');
            $t->unsignedBigInteger('project_head_subheads_id');
            $t->string('type'); $t->integer('is_active');
            $t->decimal('amount_in',18,2); $t->decimal('amount_out',18,2);
            $t->string('delete_reason')->nullable();
            $t->unsignedBigInteger('update_by')->nullable(); $t->timestamps();
        });
        Schema::create('project_voucher_display_numbers', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->string('source_kind');
            $t->unsignedBigInteger('source_id'); $t->string('series'); $t->string('display_number');
        });
        Schema::create('daily_voucher_approvals', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->string('source_table');
            $t->unsignedBigInteger('source_id'); $t->string('voucher_type'); $t->string('status');
        });
        Schema::create('daily_voucher_approval_events', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('approval_id');
            $t->string('event'); $t->unsignedBigInteger('actor_id');
            $t->text('remarks')->nullable(); $t->timestamp('created_at');
        });
        Schema::create('finance_bank_voucher_allocations', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->string('source_method');
            $t->unsignedBigInteger('source_id'); $t->string('voucher_type');
            $t->unsignedBigInteger('voucher_id'); $t->decimal('amount',18,2);
        });
        Schema::create('finance_bank_voucher_allocation_releases', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('allocation_id'); $t->unique('allocation_id');
            $t->string('source_method'); $t->unsignedBigInteger('source_id');
            $t->string('voucher_type'); $t->unsignedBigInteger('voucher_id');
            $t->decimal('amount',18,2); $t->string('action');
            $t->string('reason'); $t->unsignedBigInteger('released_by');
            $t->timestamp('released_at'); $t->timestamps();
        });
        DB::table('projects')->insert(['id'=>3]);
        DB::table('project_head_subheads')->insert(['id'=>7,'project_id'=>3]);
        DB::table('finance_online_intakes')->insert([
            'id'=>55,'project_id'=>3,'status'=>'confirmed','amount'=>'500.00',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    private function voucher(int $sourceId, string $series, string $status, float $amount): void
    {
        $receipt=in_array($series,['CR','BOR'],true);
        $active=$status==='pending'?0:1;
        DB::table('customer_ledger')->insert([
            'id'=>$sourceId,'project_id'=>3,'transaction_type'=>$series,'payment_type'=>2,
            'is_active'=>$active,'amount_in'=>$receipt?$amount:0,
            'amount_out'=>$receipt?0:$amount,
        ]);
        DB::table('ledgers')->insert([
            'project_head_subheads_id'=>7,'customer_ledger_id'=>$sourceId,
            'type'=>$series,'is_active'=>$active,'amount_in'=>$receipt?$amount:0,
            'amount_out'=>$receipt?0:$amount,
        ]);
        DB::table('project_voucher_display_numbers')->insert([
            'project_id'=>3,'source_kind'=>'customer_ledger','source_id'=>$sourceId,
            'series'=>$series,'display_number'=>$series.'-'.$sourceId,
        ]);
        DB::table('finance_bank_voucher_allocations')->insert([
            'project_id'=>3,'source_method'=>'online','source_id'=>55,
            'voucher_type'=>$series,'voucher_id'=>$sourceId,'amount'=>$amount,
        ]);
        DB::table('daily_voucher_approvals')->insert([
            'project_id'=>3,'source_table'=>'customer_ledger','source_id'=>$sourceId,
            'voucher_type'=>$series,'status'=>$status,
        ]);
    }

    public function test_multiple_native_bor_bop_vouchers_are_voided_and_released_atomically(): void
    {
        $this->voucher(31,'BOR','approved',300);
        $this->voucher(32,'BOP','approved',200);
        $result=app(BankManagementSourceMutation::class)
            ->withdraw(3,'online',55,7,'Correct verified source');
        self::assertSame(2,$result['released_count']);
        self::assertSame('withdrawn',DB::table('finance_online_intakes')->where('id',55)->value('status'));
        self::assertSame(2,DB::table('finance_bank_voucher_allocation_releases')->count());
        self::assertSame(0,DB::table('customer_ledger')->where('is_active',1)->count());
        self::assertSame(0,DB::table('ledgers')->where('is_active',1)->count());
        self::assertSame(2,DB::table('finance_bank_voucher_allocations')->count());
    }

    public function test_linked_online_edit_creates_new_unconfirmed_source_without_reusing_old_ledger(): void
    {
        DB::table('finance_online_intakes')->where('id',55)->update([
            'reference'=>'ORIGINAL','reference_key'=>'ORIGINAL',
        ]);
        $this->voucher(31,'BOR','approved',500);
        $result=app(BankManagementSourceMutation::class)->replace(
            3,'online',55,7,'Correct bank statement amount',[
                'reference'=>'CORRECTED-1','transaction_date'=>'2026-11-22',
                'amount'=>'500.00','type'=>'receipt','sending_bank'=>'A',
                'sending_account'=>'123','receiving_bank'=>'B','receiving_account'=>'456',
                'sender_name'=>'Customer','receiver_name'=>'Office','purpose'=>'Corrected',
                'account_mapping_id'=>7,
            ]);
        self::assertSame('pending',$result['new_status']);
        self::assertNotSame(55,$result['replacement_source_id']);
        self::assertSame('withdrawn',DB::table('finance_online_intakes')->where('id',55)->value('status'));
        self::assertSame('pending',DB::table('finance_online_intakes')
            ->where('id',$result['replacement_source_id'])->value('status'));
        self::assertSame('CORRECTED-1',DB::table('finance_online_intakes')
            ->where('id',$result['replacement_source_id'])->value('reference'));
        self::assertSame(0,(int)DB::table('ledgers')->where('customer_ledger_id',31)->value('is_active'));
        self::assertSame(1,DB::table('finance_bank_voucher_allocation_releases')->count());
    }

    public function test_pending_second_voucher_rolls_back_first_release_and_source_status(): void
    {
        $this->voucher(31,'BOR','approved',300);
        $this->voucher(32,'BOP','pending',200);
        try {
            app(BankManagementSourceMutation::class)->withdraw(
                3,'online',55,7,'Must rollback all sources');
            self::fail('Withdrawal should not succeed with pending approval');
        } catch (\DomainException $e) {
            self::assertStringContainsString('Pending approval',$e->getMessage());
        }
        self::assertSame('confirmed',DB::table('finance_online_intakes')->where('id',55)->value('status'));
        self::assertSame(0,DB::table('finance_bank_voucher_allocation_releases')->count());
        self::assertSame(1,DB::table('ledgers')->where('customer_ledger_id',31)->value('is_active'));
    }
}
