<?php

namespace Tests\Feature\Finance;

use App\Models\CustomerLedger;
use App\Services\Finance\ExtraChargeCashEditor;
use App\Services\Finance\ExtraChargeBankSettlement;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ExtraChargeCashEditorTest extends TestCase
{
    private string $previous;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previous=(string)config('database.default');
        config(['database.connections.extra_charge_editor_test'=>[
            'driver'=>'sqlite','database'=>':memory:','prefix'=>'',
            'foreign_key_constraints'=>true,
        ],'database.default'=>'extra_charge_editor_test']);
        DB::purge('extra_charge_editor_test');
        Schema::create('projects',fn(Blueprint $t)=>$t->id());
        Schema::create('project_head_subheads',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');$t->softDeletes();
        });
        Schema::create('customer_ledger',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('customer_id');
            $t->unsignedBigInteger('plot_id');
            $t->string('transaction_type');$t->unsignedBigInteger('type_id');
            $t->string('reference');$t->date('date');
            $t->string('description')->nullable();
            $t->unsignedTinyInteger('payment_type')->nullable();
            $t->unsignedTinyInteger('passing_status')->nullable();
            $t->decimal('amount_in',15,2)->default(0);
            $t->decimal('amount_out',15,2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->boolean('is_approve')->default(false);
            $t->timestamps();
        });
        Schema::create('ledgers',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('customer_ledger_id');
            $t->string('type');$t->unsignedBigInteger('type_id');
            $t->unsignedBigInteger('voucher_number');
            $t->unsignedBigInteger('project_head_subheads_id');
            $t->date('date');$t->string('reference');$t->string('detail');
            $t->decimal('amount_in',15,2);$t->decimal('amount_out',15,2);
            $t->boolean('is_active')->default(true);$t->timestamps();
        });
        DB::table('projects')->insert([['id'=>1],['id'=>2]]);
        DB::table('project_head_subheads')->insert([['id'=>101,'project_id'=>1],
            ['id'=>102,'project_id'=>1],['id'=>201,'project_id'=>2]]);
        DB::table('customer_ledger')->insert([
            ['id'=>10,'project_id'=>1,'customer_id'=>31,'plot_id'=>41,
                'transaction_type'=>'EC','type_id'=>50,'reference'=>'CHARGE-1',
                'date'=>'2026-11-18','description'=>'Original charge',
                'amount_in'=>'250.00','amount_out'=>'0.00','is_active'=>1,
                'payment_type'=>null],
            ['id'=>20,'project_id'=>1,'customer_id'=>31,'plot_id'=>41,
                'transaction_type'=>'ECP','type_id'=>51,
                'reference'=>'EC_PAYMENT#10#CASH-1','date'=>'2026-11-18',
                'description'=>'Original payment','payment_type'=>1,
                'amount_in'=>'100.00','amount_out'=>'0.00','is_active'=>1],
        ]);
        foreach([[10,'EC',50,'CHARGE-1','250.00',101,102],
                 [20,'ECP',51,'EC_PAYMENT#10#CASH-1','100.00',101,102]]
                as [$sourceId,$series,$number,$reference,$amount,$debit,$credit]){
            DB::table('ledgers')->insert([
                ['customer_ledger_id'=>$sourceId,'type'=>$series,'type_id'=>$number,
                    'voucher_number'=>$number,'reference'=>$reference,
                    'project_head_subheads_id'=>$debit,'date'=>'2026-11-18',
                    'detail'=>'Original','amount_in'=>$amount,'amount_out'=>'0.00','is_active'=>1],
                ['customer_ledger_id'=>$sourceId,'type'=>$series,'type_id'=>$number,
                    'voucher_number'=>$number,'reference'=>$reference,
                    'project_head_subheads_id'=>$credit,'date'=>'2026-11-18',
                    'detail'=>'Original','amount_in'=>'0.00','amount_out'=>$amount,'is_active'=>1],
            ]);
        }
    }

    protected function tearDown(): void
    {
        config(['database.default'=>$this->previous]);
        DB::purge('extra_charge_editor_test');
        parent::tearDown();
    }

    public function test_cash_charge_and_payment_update_keep_source_and_ledger_ids_balanced(): void
    {
        $editor=new ExtraChargeCashEditor();
        $ids=DB::table('ledgers')->orderBy('id')->pluck('id')->all();
        $editor->update(1,10,'2026-11-19','300.00','Corrected charge');
        $editor->update(1,20,'2026-11-19','125.00','Corrected payment');
        self::assertSame(2,DB::table('customer_ledger')->count());
        self::assertSame(4,DB::table('ledgers')->count());
        self::assertSame($ids,DB::table('ledgers')->orderBy('id')->pluck('id')->all());
        self::assertSame(300.0,(float)DB::table('customer_ledger')->where('id',10)->value('amount_in'));
        self::assertSame(125.0,(float)DB::table('customer_ledger')->where('id',20)->value('amount_in'));
        self::assertSame(125.0,(float)DB::table('ledgers')
            ->where('customer_ledger_id',20)->sum('amount_in'));
        $charge=CustomerLedger::query()->whereKey(10)->firstOrFail();
        $balance=(new ExtraChargeBankSettlement())->balances($charge);
        self::assertSame(30000,$balance['charged_cents']);
        self::assertSame(12500,$balance['paid_cents']);
        try {
            $editor->update(1,10,'2026-11-19','100.00','Too small');
            self::fail('A charge cannot drop below existing payments.');
        }catch(\DomainException $e){
            self::assertStringContainsString('less than cleared',$e->getMessage());
        }
        self::assertSame(300.0,(float)DB::table('customer_ledger')->where('id',10)->value('amount_in'));
    }

    public function test_cash_payment_and_charge_delete_archive_all_legs_without_reversal(): void
    {
        $editor=new ExtraChargeCashEditor();
        try {
            $editor->delete(1,10);
            self::fail('A charge with a paid ECP cannot be deleted.');
        }catch(\DomainException $e){
            self::assertStringContainsString('linked payments',$e->getMessage());
        }
        $editor->delete(1,20);
        self::assertSame(0,(int)DB::table('customer_ledger')->where('id',20)->value('is_active'));
        self::assertSame(0,DB::table('ledgers')->where('customer_ledger_id',20)
            ->where('is_active',1)->count());
        $charge=CustomerLedger::query()->whereKey(10)->firstOrFail();
        $balance=(new ExtraChargeBankSettlement())->balances($charge);
        self::assertSame(0,$balance['paid_cents']);
        $editor->delete(1,10);
        self::assertSame(2,DB::table('customer_ledger')->count());
        self::assertSame(4,DB::table('ledgers')->count());
        self::assertSame(0,DB::table('ledgers')->where('is_active',1)->count());
        self::assertSame(0,DB::table('customer_ledger')->where('is_active',1)->count());
    }

    public function test_cross_project_and_corrupt_payment_ledgers_cannot_change_source(): void
    {
        $editor=new ExtraChargeCashEditor();
        try {
            $editor->update(2,20,'2026-11-19','110.00','Wrong project');
            self::fail('Foreign project payment should be blocked.');
        }catch(\DomainException|\Illuminate\Database\RecordNotFoundException $e){
            self::assertNotEmpty($e->getMessage());
        }
        $legId=DB::table('ledgers')->where('customer_ledger_id',20)
            ->orderBy('id')->value('id');
        DB::table('ledgers')->where('id',$legId)
            ->update(['project_head_subheads_id'=>201]);
        try {
            $editor->update(1,20,'2026-11-19','110.00','Corrupt');
            self::fail('Foreign ledger should block correction.');
        }catch(\DomainException $e){
            self::assertStringContainsString('identity',$e->getMessage());
        }
        self::assertSame(100.0,(float)DB::table('customer_ledger')->where('id',20)->value('amount_in'));
    }
}
