<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\StockDocumentEditor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class StockDocumentEditorTest extends TestCase
{
    private string $previous;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previous=(string)config('database.default');
        config(['database.connections.stock_editor_test'=>[
            'driver'=>'sqlite','database'=>':memory:','prefix'=>'',
            'foreign_key_constraints'=>true,
        ],'database.default'=>'stock_editor_test']);
        DB::purge('stock_editor_test');
        Schema::create('projects',fn(Blueprint $t)=>$t->id());
        Schema::create('project_head_subheads',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');
        });
        Schema::create('stock_entries',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');$t->unsignedBigInteger('party_id');
            $t->string('slip_no');$t->date('entry_date');
            $t->string('flow');$t->text('reason')->nullable();
            $t->timestamp('billed_at')->nullable();$t->softDeletes();$t->timestamps();
        });
        Schema::create('stock_bills',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');$t->unsignedBigInteger('party_id');
            $t->unsignedBigInteger('entry_id')->nullable();
            $t->string('bill_no');$t->date('bill_date');
            $t->string('type');$t->bigInteger('total_minor');$t->softDeletes();$t->timestamps();
        });
        foreach(['stock_entry_lines','stock_bill_lines'] as $table) {
            Schema::create($table,function(Blueprint $t)use($table){
                $t->id();$t->unsignedBigInteger('project_id');
                $t->unsignedBigInteger($table==='stock_entry_lines'?'entry_id':'bill_id');
                $t->unsignedBigInteger('item_id');$t->decimal('qty',16,3);
                $t->bigInteger('rate_minor');$t->bigInteger('amount_minor');
                $t->softDeletes();$t->timestamps();
            });
        }
        Schema::create('stock_movements',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('item_id');$t->unsignedBigInteger('party_id');
            $t->unsignedBigInteger('source_id');$t->unsignedBigInteger('source_line_id');
            $t->string('source_type');$t->string('direction');
            $t->date('movement_date');$t->decimal('qty',16,3);
            $t->bigInteger('rate_minor');$t->bigInteger('amount_minor');
            $t->boolean('is_active')->default(true);$t->softDeletes();$t->timestamps();
        });
        Schema::create('stock_project_item_balances',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');$t->unsignedBigInteger('item_id');
            $t->decimal('on_hand_qty',16,3);$t->timestamps();
        });
        Schema::create('stock_project_item_definitions',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');$t->unsignedBigInteger('item_id');
            $t->boolean('is_active')->default(true);$t->softDeletes();
        });
        Schema::create('customer_ledger',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');$t->string('transaction_type');
            $t->string('reference');$t->date('date');$t->string('description');
            $t->decimal('amount_in',16,2);$t->decimal('amount_out',16,2);
            $t->boolean('is_active')->default(true);$t->timestamps();
        });
        Schema::create('ledgers',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('customer_ledger_id');
            $t->unsignedBigInteger('project_head_subheads_id');
            $t->string('type');$t->unsignedBigInteger('type_id');
            $t->unsignedBigInteger('voucher_number');
            $t->string('reference');$t->date('date');
            $t->decimal('amount_in',16,2);$t->decimal('amount_out',16,2);
            $t->boolean('is_active')->default(true);$t->string('detail');$t->timestamps();
        });
        DB::table('projects')->insert([['id'=>1],['id'=>2]]);
        DB::table('project_head_subheads')->insert([['id'=>101,'project_id'=>1],['id'=>102,'project_id'=>1]]);
        DB::table('stock_entries')->insert(['id'=>11,'project_id'=>1,'party_id'=>3,
            'slip_no'=>'S-11','entry_date'=>'2026-11-18',
            'flow'=>'IN','reason'=>'Received','billed_at'=>null]);
        DB::table('stock_entry_lines')->insert([
            'id'=>21,'project_id'=>1,'entry_id'=>11,'item_id'=>31,
            'qty'=>'5.000','rate_minor'=>1000,'amount_minor'=>5000,
        ]);
        DB::table('stock_movements')->insert([
            'id'=>41,'project_id'=>1,'party_id'=>3,'source_type'=>'entry',
            'source_id'=>11,'source_line_id'=>21,'item_id'=>31,'qty'=>'5.000',
            'rate_minor'=>1000,'amount_minor'=>5000,'direction'=>'IN',
            'movement_date'=>'2026-11-18','is_active'=>1,
        ]);
        DB::table('stock_project_item_balances')->insert([
            'project_id'=>1,'item_id'=>31,'on_hand_qty'=>'5.000',
        ]);
        DB::table('stock_project_item_definitions')->insert([
            'project_id'=>1,'item_id'=>31,'is_active'=>1,
        ]);
        DB::table('customer_ledger')->insert([
            'id'=>51,'project_id'=>1,'transaction_type'=>'JV',
            'reference'=>'STOCK-ENTRY:11','date'=>'2026-11-18',
            'description'=>'Received','amount_in'=>'0.00',
            'amount_out'=>'50.00','is_active'=>1,
        ]);
        DB::table('ledgers')->insert([
            ['id'=>61,'customer_ledger_id'=>51,'project_head_subheads_id'=>101,
                'type'=>'JV','type_id'=>18,'voucher_number'=>12,
                'reference'=>'STOCK-ENTRY:11','date'=>'2026-11-18',
                'amount_in'=>'50.00','amount_out'=>'0.00',
                'detail'=>'Received','is_active'=>1],
            ['id'=>62,'customer_ledger_id'=>51,'project_head_subheads_id'=>102,
                'type'=>'JV','type_id'=>18,'voucher_number'=>12,
                'reference'=>'STOCK-ENTRY:11','date'=>'2026-11-18',
                'amount_in'=>'0.00','amount_out'=>'50.00',
                'detail'=>'Received','is_active'=>1],
        ]);
    }

    protected function tearDown(): void
    {
        config(['database.default'=>$this->previous]);
        DB::purge('stock_editor_test');
        parent::tearDown();
    }

    public function test_stock_entry_same_id_correction_updates_inventory_and_exact_linked_ledger(): void
    {
        $editor=new StockDocumentEditor();
        $editor->update(1,'entry',11,'2026-11-19',[
            ['detail_id'=>21,'item_id'=>31,'qty'=>'8.000','rate'=>1000],
        ],'Stock correction');
        self::assertSame(8.0,(float)DB::table('stock_project_item_balances')
            ->where('project_id',1)->where('item_id',31)->value('on_hand_qty'));
        self::assertSame(8.0,(float)DB::table('stock_entry_lines')->where('id',21)->value('qty'));
        self::assertSame(8.0,(float)DB::table('stock_movements')->where('id',41)->value('qty'));
        self::assertSame(80.0,(float)DB::table('customer_ledger')
            ->where('id',51)->value('amount_out'));
        self::assertSame(80.0,(float)DB::table('ledgers')->where('id',61)->value('amount_in'));
        self::assertSame(80.0,(float)DB::table('ledgers')->where('id',62)->value('amount_out'));
        $editor->update(1,'entry',11,'2026-11-19',[
            ['detail_id'=>21,'item_id'=>31,'qty'=>'8.000','rate'=>1000],
        ],'Stock correction');
        self::assertSame(1,DB::table('stock_movements')->count());
    }

    public function test_stock_entry_delete_archives_source_movement_and_financial_rows(): void
    {
        $editor=new StockDocumentEditor();
        $editor->delete(1,'entry',11);
        self::assertSame(0,(int)DB::table('stock_entry_lines')->where('id',21)
            ->whereNull('deleted_at')->count());
        self::assertSame(0,(int)DB::table('stock_movements')->where('id',41)->value('is_active'));
        self::assertSame(0.0,(float)DB::table('stock_project_item_balances')
            ->where('project_id',1)->where('item_id',31)->value('on_hand_qty'));
        self::assertSame(0,DB::table('customer_ledger')->where('is_active',1)->count());
        self::assertSame(0,DB::table('ledgers')->where('is_active',1)->count());
        self::assertSame(2,DB::table('ledgers')->count());
        self::assertNotNull(DB::table('stock_entries')->where('id',11)->value('deleted_at'));
    }

    public function test_stock_correction_rejects_negative_inventory_and_foreign_detail_atomically(): void
    {
        $editor=new StockDocumentEditor();
        $before=DB::table('stock_project_item_balances')
            ->where('project_id',1)->where('item_id',31)->value('on_hand_qty');
        // Another stock issue already consumed all received units. Reducing
        // the original IN quantity must not make inventory negative.
        DB::table('stock_project_item_balances')->where('project_id',1)
            ->where('item_id',31)->update(['on_hand_qty'=>'0.000']);
        try {
            $editor->update(1,'entry',11,'2026-11-19',[
                ['detail_id'=>21,'item_id'=>31,'qty'=>'1.000','rate'=>1000],
            ],'Unsafe reduced receipt');
            self::fail('Insufficient stock must abort transaction.');
        }catch(\DomainException $e) {
            self::assertStringContainsString('negative inventory',$e->getMessage());
        }
        self::assertSame(5.0,(float)DB::table('stock_entry_lines')
            ->where('id',21)->value('qty'));
        self::assertSame(50.0,(float)DB::table('customer_ledger')
            ->where('id',51)->value('amount_out'));
        DB::table('stock_project_item_balances')->where('project_id',1)
            ->where('item_id',31)->update(['on_hand_qty'=>$before]);
        try {
            $editor->update(1,'entry',11,'2026-11-19',[
                ['detail_id'=>999,'item_id'=>31,'qty'=>'6.000','rate'=>1000],
            ],'Foreign identity');
            self::fail('Unknown detail ID must be refused.');
        }catch(\DomainException $e) {
            self::assertStringContainsString('another voucher',$e->getMessage());
        }
        self::assertSame(1,DB::table('stock_movements')->count());
        self::assertSame(5.0,(float)DB::table('stock_project_item_balances')
            ->where('project_id',1)->where('item_id',31)->value('on_hand_qty'));
    }

    public function test_stock_bill_purchase_edit_and_delete_preserve_financial_and_entry_fk(): void
    {
        DB::table('stock_bills')->insert([
            'id'=>12,'project_id'=>1,'party_id'=>3,'entry_id'=>null,
            'bill_no'=>'B-12','bill_date'=>'2026-11-18',
            'type'=>'purchase','total_minor'=>4000,
        ]);
        DB::table('stock_bill_lines')->insert([
            'id'=>22,'project_id'=>1,'bill_id'=>12,
            'item_id'=>31,'qty'=>'4.000','rate_minor'=>1000,
            'amount_minor'=>4000,
        ]);
        DB::table('stock_movements')->insert([
            'id'=>42,'project_id'=>1,'party_id'=>3,'source_type'=>'bill',
            'source_id'=>12,'source_line_id'=>22,'item_id'=>31,
            'qty'=>'4.000','rate_minor'=>1000,'amount_minor'=>4000,
            'direction'=>'IN','movement_date'=>'2026-11-18','is_active'=>1,
        ]);
        DB::table('stock_project_item_balances')->where('project_id',1)
            ->where('item_id',31)->update(['on_hand_qty'=>'9.000']);
        DB::table('customer_ledger')->insert([
            'id'=>52,'project_id'=>1,'transaction_type'=>'JV',
            'reference'=>'STOCK-BILL:12','date'=>'2026-11-18',
            'description'=>'Purchased','amount_in'=>'0.00',
            'amount_out'=>'40.00','is_active'=>1,
        ]);
        DB::table('ledgers')->insert([
            ['id'=>63,'customer_ledger_id'=>52,'project_head_subheads_id'=>101,
                'type'=>'JV','type_id'=>19,'voucher_number'=>13,
                'reference'=>'STOCK-BILL:12','date'=>'2026-11-18',
                'amount_in'=>'40.00','amount_out'=>'0.00',
                'detail'=>'Purchased','is_active'=>1],
            ['id'=>64,'customer_ledger_id'=>52,'project_head_subheads_id'=>102,
                'type'=>'JV','type_id'=>19,'voucher_number'=>13,
                'reference'=>'STOCK-BILL:12','date'=>'2026-11-18',
                'amount_in'=>'0.00','amount_out'=>'40.00',
                'detail'=>'Purchased','is_active'=>1],
        ]);
        $editor=new StockDocumentEditor();
        $editor->update(1,'bill',12,'2026-11-19',[
            ['detail_id'=>22,'item_id'=>31,'qty'=>'6.000','rate'=>1000],
        ]);
        self::assertSame(11.0,(float)DB::table('stock_project_item_balances')
            ->where('project_id',1)->where('item_id',31)->value('on_hand_qty'));
        self::assertSame(6000,(int)DB::table('stock_bills')->where('id',12)->value('total_minor'));
        self::assertSame(60.0,(float)DB::table('customer_ledger')->where('id',52)->value('amount_out'));
        self::assertSame(60.0,(float)DB::table('ledgers')->where('id',63)->value('amount_in'));
        $editor->delete(1,'bill',12);
        self::assertSame(5.0,(float)DB::table('stock_project_item_balances')
            ->where('project_id',1)->where('item_id',31)->value('on_hand_qty'));
        self::assertSame(0,(int)DB::table('customer_ledger')->where('id',52)->value('is_active'));
        self::assertSame(0,DB::table('ledgers')->where('customer_ledger_id',52)->where('is_active',1)->count());
        self::assertSame(4,DB::table('ledgers')->count());
        self::assertNotNull(DB::table('stock_bills')->where('id',12)->value('deleted_at'));
    }

    public function test_foreign_project_or_billed_entry_refuses_without_inventory_mutation(): void
    {
        $editor=new StockDocumentEditor();
        try{
            $editor->update(2,'entry',11,'2026-11-19',[
                ['detail_id'=>21,'item_id'=>31,'qty'=>'8.000','rate'=>1000],
            ]);
            self::fail('Wrong project cannot modify stock.');
        }catch(\DomainException|\Illuminate\Database\RecordNotFoundException $e){
            self::assertNotEmpty($e->getMessage());
        }
        DB::table('stock_entries')->where('id',11)->update(['billed_at'=>'2026-11-19']);
        try{
            $editor->delete(1,'entry',11);
            self::fail('Billed stock entry must not be deleted.');
        }catch(\DomainException $e){
            self::assertStringContainsString('linked to a bill',$e->getMessage());
        }
        self::assertSame(5.0,(float)DB::table('stock_project_item_balances')
            ->where('project_id',1)->where('item_id',31)->value('on_hand_qty'));
    }
}
