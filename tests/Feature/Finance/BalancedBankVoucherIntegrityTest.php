<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\BalancedBankVoucherIntegrity;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class BalancedBankVoucherIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('sqlite',DB::getDefaultConnection());
        self::assertSame(':memory:',DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        Schema::create('head_accountings',function(Blueprint $t){
            $t->id();$t->boolean('is_active');
        });
        Schema::create('subhead_accountings',function(Blueprint $t){
            $t->id();$t->boolean('is_active');
        });
        Schema::create('project_head_subheads',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('head_accounting_id');
            $t->unsignedBigInteger('subhead_accounting_id');
        });
        Schema::create('project_bank_accounts',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('account_mapping_id');$t->boolean('is_active');
        });
        DB::table('head_accountings')->insert(['id'=>1,'is_active'=>1]);
        DB::table('subhead_accountings')->insert(['id'=>1,'is_active'=>1]);
        DB::table('project_head_subheads')->insert([
            ['id'=>11,'project_id'=>1,'head_accounting_id'=>1,'subhead_accounting_id'=>1],
            ['id'=>12,'project_id'=>1,'head_accounting_id'=>1,'subhead_accounting_id'=>1],
            ['id'=>13,'project_id'=>2,'head_accounting_id'=>1,'subhead_accounting_id'=>1],
        ]);
        DB::table('project_bank_accounts')->insert([
            ['id'=>21,'project_id'=>1,'account_mapping_id'=>11,'is_active'=>1],
            ['id'=>22,'project_id'=>1,'account_mapping_id'=>12,'is_active'=>0],
            ['id'=>23,'project_id'=>2,'account_mapping_id'=>13,'is_active'=>1],
        ]);
    }

    private function source(string $series): object
    {
        return (object)[
            'transaction_type'=>$series==='BOR'?'BOR':'BOP',
            'amount_in'=>$series==='BOR'?'120.00':'0.00',
            'amount_out'=>$series==='BOP'?'120.00':'0.00',
        ];
    }

    private function allocation(): object
    {
        return (object)['bank_account_id'=>21,'amount'=>'120.00'];
    }

    public function test_new_bor_requires_exact_bank_debit_and_party_credit(): void
    {
        $legs=collect([
            (object)['project_head_subheads_id'=>12,'type'=>'BOR',
                'amount_in'=>'0.00','amount_out'=>'120.00'],
            (object)['project_head_subheads_id'=>11,'type'=>'BOR',
                'amount_in'=>'120.00','amount_out'=>'0.00'],
        ]);
        BalancedBankVoucherIntegrity::assert(1,'BOR',$this->source('BOR'),$this->allocation(),$legs);
        $this->assertCount(2,$legs);
        $legs[0]->amount_in='120.00';
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('BANK_VOUCHER_DOUBLE_ENTRY_DIRECTION_INVALID');
        BalancedBankVoucherIntegrity::assert(1,'BOR',$this->source('BOR'),$this->allocation(),$legs);
    }

    public function test_new_bop_requires_exact_bank_credit_and_party_debit(): void
    {
        $legs=collect([
            (object)['project_head_subheads_id'=>12,'type'=>'BOP',
                'amount_in'=>'120.00','amount_out'=>'0.00'],
            (object)['project_head_subheads_id'=>11,'type'=>'BOP',
                'amount_in'=>'0.00','amount_out'=>'120.00'],
        ]);
        BalancedBankVoucherIntegrity::assert(1,'BOP',$this->source('BOP'),$this->allocation(),$legs);
        $this->assertCount(2,$legs);
    }

    public function test_bank_counter_selection_rejects_inactive_bank(): void
    {
        $this->expectException(\DomainException::class);
        DB::transaction(fn()=>BalancedBankVoucherIntegrity::bankAccount(1,22,12));
    }

    public function test_bank_counter_selection_rejects_cross_project_bank(): void
    {
        $this->expectException(\DomainException::class);
        DB::transaction(fn()=>BalancedBankVoucherIntegrity::bankAccount(1,23,12));
    }

    public function test_bank_counter_selection_rejects_matching_party_account(): void
    {
        $this->expectException(\DomainException::class);
        DB::transaction(fn()=>BalancedBankVoucherIntegrity::bankAccount(1,21,11));
    }

    public function test_bank_counter_selection_accepts_active_project_bank(): void
    {
        $bank=DB::transaction(fn()=>BalancedBankVoucherIntegrity::bankAccount(1,21,12));
        $this->assertSame(11,(int)$bank->account_mapping_id);
    }
}
