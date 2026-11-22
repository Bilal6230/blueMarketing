<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\BankManagementSourceMutation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class BankManagementSourceMutationTest extends TestCase
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
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('status'); $t->string('reference'); $t->string('reference_key');
            $t->decimal('amount',18,2); $t->string('type');
            $t->string('transaction_date'); $t->text('remarks')->nullable();
            $t->unsignedBigInteger('account_mapping_id'); $t->timestamps();
        });
        Schema::create('finance_cheque_intakes', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('status'); $t->string('cheque_number'); $t->string('bank_name');
            $t->decimal('amount',18,2); $t->string('direction'); $t->timestamps();
        });
        Schema::create('finance_bank_voucher_allocations', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('source_method'); $t->unsignedBigInteger('source_id');
            $t->string('voucher_type'); $t->unsignedBigInteger('voucher_id');
            $t->decimal('amount',18,2);
        });
        Schema::create('finance_bank_voucher_allocation_releases', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('allocation_id');
            $t->string('source_method'); $t->unsignedBigInteger('source_id');
            $t->string('voucher_type'); $t->unsignedBigInteger('voucher_id');
            $t->decimal('amount',18,2); $t->string('action'); $t->string('reason');
            $t->unsignedBigInteger('released_by'); $t->timestamp('released_at');
            $t->timestamps(); $t->unique('allocation_id');
        });
        Schema::create('finance_online_voucher_claims', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('online_intake_id');
        });
        Schema::create('finance_cheque_voucher_claims', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('cheque_intake_id');
        });
        Schema::create('finance_online_confirmation_events', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('online_intake_id');
        });
        Schema::create('finance_cheque_intake_actions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('cheque_intake_id');
            $t->string('action'); $t->string('from_status'); $t->string('to_status');
            $t->string('action_date'); $t->string('bank_name');
            $t->decimal('original_amount',18,2); $t->string('reason');
            $t->unsignedBigInteger('actor_id'); $t->timestamp('created_at');
        });
        DB::table('projects')->insert([['id'=>3],['id'=>4]]);
        DB::table('project_head_subheads')->insert([['id'=>7,'project_id'=>3],['id'=>8,'project_id'=>4]]);
        DB::table('finance_online_intakes')->insert([
            ['id'=>10,'project_id'=>3,'status'=>'pending','reference'=>'A1','reference_key'=>'A1',
                'amount'=>'100.00','type'=>'receipt','transaction_date'=>'2026-11-22',
                'account_mapping_id'=>7,'created_at'=>now(),'updated_at'=>now()],
            ['id'=>11,'project_id'=>4,'status'=>'pending','reference'=>'A1','reference_key'=>'A1',
                'amount'=>'200.00','type'=>'receipt','transaction_date'=>'2026-11-22',
                'account_mapping_id'=>8,'created_at'=>now(),'updated_at'=>now()],
        ]);
        DB::table('finance_cheque_intakes')->insert([
            'id'=>12,'project_id'=>3,'status'=>'received',
            'cheque_number'=>'CHQ1','bank_name'=>'ABC','amount'=>'100.00',
            'direction'=>'in','created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function test_unlinked_pending_online_edit_and_withdraw_are_project_scoped(): void
    {
        $mutation=app(BankManagementSourceMutation::class);
        $mutation->update(3,'online',10,[
            'reference'=>'A2','amount'=>'150.00','account_mapping_id'=>7,
        ]);
        self::assertSame('A2',DB::table('finance_online_intakes')->where('id',10)->value('reference'));
        self::assertSame('A1',DB::table('finance_online_intakes')->where('id',11)->value('reference'));
        $result=$mutation->withdraw(3,'online',10,5,'Incorrect pending source');
        self::assertSame('withdrawn',$result['status']);
        self::assertSame(0,$result['released_count']);
        self::assertSame('pending',DB::table('finance_online_intakes')->where('id',11)->value('status'));
        self::assertSame(0,DB::table('finance_bank_voucher_allocation_releases')->count());
    }

    public function test_unlinked_cheque_is_withdrawn_with_retained_evidence(): void
    {
        $result=app(BankManagementSourceMutation::class)
            ->withdraw(3,'cheque',12,5,'Wrong cheque amount');
        self::assertSame(0,$result['released_count']);
        self::assertSame('withdrawn',DB::table('finance_cheque_intakes')->where('id',12)->value('status'));
        self::assertSame('Wrong cheque amount',
            DB::table('finance_cheque_intake_actions')->where('cheque_intake_id',12)->value('reason'));
    }

    public function test_historical_claim_blocks_direct_edit_and_withdraw(): void
    {
        DB::table('finance_online_voucher_claims')->insert([
            'project_id'=>3,'online_intake_id'=>10,
        ]);
        $mutation=app(BankManagementSourceMutation::class);
        try {
            $mutation->update(3,'online',10,['amount'=>'200.00']);
            self::fail('Claimed source was modified');
        } catch (\DomainException $e) {
            self::assertStringContainsString('claim',strtolower($e->getMessage()));
        }
        try {
            $mutation->withdraw(3,'online',10,5,'Attempt despite claim');
            self::fail('Claimed source was withdrawn');
        } catch (\DomainException $e) {
            self::assertStringContainsString('claim',strtolower($e->getMessage()));
        }
        self::assertSame('pending',DB::table('finance_online_intakes')->where('id',10)->value('status'));
    }

    public function test_cross_project_source_id_never_mutates(): void
    {
        $this->expectException(\DomainException::class);
        app(BankManagementSourceMutation::class)->update(3,'online',11,['amount'=>'900.00']);
    }
}
