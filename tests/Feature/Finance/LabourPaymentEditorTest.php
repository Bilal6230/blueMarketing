<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\LabourPaymentEditor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LabourPaymentEditorTest extends TestCase
{
    private string $previousConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousConnection=(string)config('database.default');
        config(['database.connections.labour_editor_test'=>[
            'driver'=>'sqlite','database'=>':memory:','prefix'=>'',
            'foreign_key_constraints'=>true,
        ],'database.default'=>'labour_editor_test']);
        DB::purge('labour_editor_test');
        Schema::create('projects',fn(Blueprint $t)=>$t->id());
        Schema::create('project_head_subheads',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');
        });
        Schema::create('labours',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');
        });
        Schema::create('customer_ledger',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');$t->string('transaction_type');
            $t->unsignedBigInteger('type_id');$t->string('reference');
            $t->date('date');$t->string('description');
            $t->unsignedTinyInteger('payment_type');
            $t->decimal('amount_in',15,2);$t->decimal('amount_out',15,2);
            $t->boolean('is_active')->default(true);$t->timestamps();
        });
        Schema::create('ledgers',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('customer_ledger_id');
            $t->unsignedBigInteger('project_head_subheads_id');
            $t->string('type');$t->unsignedBigInteger('type_id');
            $t->unsignedBigInteger('voucher_number');
            $t->string('reference');$t->string('detail');
            $t->date('date');$t->decimal('amount_in',15,2);
            $t->decimal('amount_out',15,2);
            $t->boolean('is_active')->default(true);$t->timestamps();
        });
        Schema::create('labour_ledgers',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('labour_id');
            $t->unsignedBigInteger('customer_ledger_id')->nullable();
            $t->unsignedBigInteger('contractor_id')->nullable();
            $t->decimal('amount',15,2);$t->date('date');
            $t->text('details')->nullable();$t->boolean('is_active')->default(true);
            $t->softDeletes();$t->timestamps();
        });
        DB::table('projects')->insert([['id'=>1],['id'=>2]]);
        DB::table('project_head_subheads')->insert([['id'=>101,'project_id'=>1],['id'=>201,'project_id'=>2]]);
        DB::table('labours')->insert([['id'=>11,'project_id'=>1],['id'=>12,'project_id'=>1],['id'=>13,'project_id'=>1],['id'=>21,'project_id'=>2]]);
        DB::table('customer_ledger')->insert([
            'id'=>31,'project_id'=>1,'transaction_type'=>'CP','type_id'=>7,
            'reference'=>'LABOUR-CASH-CP','date'=>'2026-11-18',
            'description'=>'Labour cash payment','payment_type'=>1,
            'amount_in'=>'0.00','amount_out'=>'300.00','is_active'=>1,
        ]);
        DB::table('ledgers')->insert([
            'id'=>41,'customer_ledger_id'=>31,'project_head_subheads_id'=>101,
            'type'=>'CP','type_id'=>7,'voucher_number'=>11,
            'reference'=>'LABOUR-CASH-CP','date'=>'2026-11-18',
            'detail'=>'Labour cash payment','amount_in'=>'0.00',
            'amount_out'=>'300.00','is_active'=>1,
        ]);
        DB::table('labour_ledgers')->insert([
            ['id'=>51,'project_id'=>1,'labour_id'=>11,'customer_ledger_id'=>31,
                'contractor_id'=>null,'amount'=>'100.00','date'=>'2026-11-18',
                'details'=>'Worker A','is_active'=>1],
            ['id'=>52,'project_id'=>1,'labour_id'=>12,'customer_ledger_id'=>31,
                'contractor_id'=>null,'amount'=>'200.00','date'=>'2026-11-18',
                'details'=>'Worker B','is_active'=>1],
        ]);
    }

    protected function tearDown(): void
    {
        config(['database.default'=>$this->previousConnection]);
        DB::purge('labour_editor_test');
        parent::tearDown();
    }

    public function test_cash_labour_cp_correction_and_noop_preserve_parent_and_detail_ids(): void
    {
        $editor=new LabourPaymentEditor();
        $data=[
            ['detail_id'=>51,'labour_id'=>11,'amount'=>'125.00'],
            ['detail_id'=>52,'labour_id'=>12,'amount'=>'225.00'],
        ];
        $editor->update(1,31,'2026-11-19','LABOUR-CORRECTED','Corrected cash CP',$data);
        self::assertSame(1,DB::table('customer_ledger')->count());
        self::assertSame(1,DB::table('ledgers')->count());
        self::assertSame(2,DB::table('labour_ledgers')->count());
        self::assertSame(350.0,(float)DB::table('customer_ledger')->where('id',31)->value('amount_out'));
        self::assertSame(350.0,(float)DB::table('ledgers')->where('id',41)->value('amount_out'));
        self::assertSame(125.0,(float)DB::table('labour_ledgers')->where('id',51)->value('amount'));
        self::assertSame(225.0,(float)DB::table('labour_ledgers')->where('id',52)->value('amount'));
        $editor->update(1,31,'2026-11-19','LABOUR-CORRECTED','Corrected cash CP',$data);
        self::assertSame(2,DB::table('labour_ledgers')->count());
    }

    public function test_add_remove_worker_and_logical_delete_preserve_original_history(): void
    {
        $editor=new LabourPaymentEditor();
        $editor->update(1,31,'2026-11-19','LABOUR-CHANGE','Updated',[
            ['detail_id'=>51,'labour_id'=>11,'amount'=>'150.00'],
            ['detail_id'=>null,'labour_id'=>13,'amount'=>'75.00'],
        ]);
        self::assertSame(3,DB::table('labour_ledgers')->count());
        self::assertSame(1,DB::table('labour_ledgers')->where('id',52)->where('is_active',0)->count());
        self::assertSame(2,DB::table('labour_ledgers')->where('is_active',1)->count());
        self::assertSame(225.0,(float)DB::table('customer_ledger')->where('id',31)->value('amount_out'));
        $editor->delete(1,31);
        self::assertSame(0,DB::table('labour_ledgers')->where('is_active',1)->count());
        self::assertSame(0,(int)DB::table('customer_ledger')->where('id',31)->value('is_active'));
        self::assertSame(0,(int)DB::table('ledgers')->where('id',41)->value('is_active'));
        self::assertSame(1,DB::table('ledgers')->count());
    }

    public function test_typed_labour_member_posting_ownership_blocks_legacy_payment_correction(): void
    {
        Schema::create('finance_posting_labour_sources',function(Blueprint $table){
            $table->unsignedBigInteger('labour_ledger_id');
            $table->unsignedBigInteger('posting_batch_id');
        });
        $linkedId=DB::table('labour_ledgers')
            ->where('customer_ledger_id',11)->value('id');
        DB::table('finance_posting_labour_sources')->insert([
            'labour_ledger_id'=>$linkedId,'posting_batch_id'=>9001,
        ]);
        $editor=new LabourPaymentEditor();
        try {
            $editor->update(1,11,'2026-11-19','LABOUR-EDIT','Incorrect legacy edit',[
                ['detail_id'=>$linkedId,'labour_id'=>31,'amount'=>'80.00'],
            ]);
            self::fail('Typed payroll batch must keep exclusive source ownership.');
        }catch(\DomainException $e){
            self::assertStringContainsString('typed payroll posting',$e->getMessage());
        }
        try {
            $editor->delete(1,11);
            self::fail('Typed payroll batch must not be bypassed by Delete.');
        }catch(\DomainException $e){
            self::assertStringContainsString('typed payroll posting',$e->getMessage());
        }
        self::assertSame(1,(int)DB::table('customer_ledger')
            ->where('id',11)->value('is_active'));
    }

    public function test_project_worker_and_fk_tampering_are_rejected_without_mutation(): void
    {
        $editor=new LabourPaymentEditor();
        $submitted=[
            ['detail_id'=>51,'labour_id'=>11,'amount'=>'100.00'],
            ['detail_id'=>null,'labour_id'=>21,'amount'=>'150.00'],
        ];
        try {
            $editor->update(1,31,'2026-11-19','LABOUR-CHG','Invalid',$submitted);
            self::fail('Foreign project worker should not be added.');
        }catch(\DomainException $e){self::assertStringContainsString('invalid',$e->getMessage());}
        self::assertSame(300.0,(float)DB::table('customer_ledger')->where('id',31)->value('amount_out'));
        DB::table('ledgers')->where('id',41)->update(['amount_out'=>'999.00']);
        try{
            $editor->delete(1,31);
            self::fail('Tampered source ledger amount must be refused.');
        }catch(\DomainException $e){self::assertStringContainsString('mismatch',$e->getMessage());}
        self::assertSame(2,DB::table('labour_ledgers')->where('is_active',1)->count());
    }
}
