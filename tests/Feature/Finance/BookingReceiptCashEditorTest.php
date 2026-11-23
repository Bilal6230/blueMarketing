<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\BookingReceiptCashEditor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class BookingReceiptCashEditorTest extends TestCase
{
    private string $beforeConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->beforeConnection=(string)config('database.default');
        config(['database.connections.booking_cash_editor_test'=>[
            'driver'=>'sqlite','database'=>':memory:','prefix'=>'',
            'foreign_key_constraints'=>true,
        ],'database.default'=>'booking_cash_editor_test']);
        DB::purge('booking_cash_editor_test');
        Schema::create('projects',fn(Blueprint $t)=>$t->id());
        Schema::create('project_head_subheads',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');
        });
        Schema::create('customer_ledger',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');
            $t->string('transaction_type');$t->unsignedBigInteger('type_id');
            $t->unsignedBigInteger('customer_id');$t->unsignedBigInteger('plot_id');
            $t->string('reference');$t->date('date');$t->string('description');
            $t->unsignedTinyInteger('payment_type');
            $t->decimal('amount_in',15,2)->default(0);
            $t->decimal('amount_out',15,2)->default(0);
            $t->boolean('is_active')->default(true);$t->timestamps();
        });
        Schema::create('ledgers',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('customer_ledger_id');
            $t->string('type');$t->unsignedBigInteger('type_id');
            $t->unsignedBigInteger('voucher_number');
            $t->unsignedBigInteger('project_head_subheads_id');
            $t->decimal('amount_in',15,2);$t->decimal('amount_out',15,2);
            $t->string('reference');$t->string('detail');$t->date('date');
            $t->boolean('is_active')->default(true);$t->timestamps();
        });
        Schema::create('booking_vouchers',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('customer_ledger_id');
            $t->unsignedBigInteger('ledger_id');
            $t->string('voucher_series');$t->unsignedBigInteger('voucher_number');
            $t->unsignedBigInteger('customer_id');$t->unsignedBigInteger('plot_id');
            $t->string('slip_reference');$t->date('receipt_date');
            $t->string('description');$t->decimal('amount',15,2);
            $t->boolean('is_active')->default(true);
            $t->unsignedBigInteger('update_by')->nullable();$t->timestamps();
        });
        DB::table('projects')->insert([['id'=>1],['id'=>2]]);
        DB::table('project_head_subheads')->insert([
            ['id'=>101,'project_id'=>1],['id'=>201,'project_id'=>2],
        ]);
        DB::table('customer_ledger')->insert([
            'id'=>11,'project_id'=>1,'transaction_type'=>'PPR','type_id'=>8,
            'customer_id'=>31,'plot_id'=>41,'reference'=>'PPR-OLD',
            'date'=>'2026-11-18','description'=>'Original receipt',
            'payment_type'=>1,'amount_in'=>'0.00','amount_out'=>'80.00',
            'is_active'=>1,
        ]);
        DB::table('ledgers')->insert([
            'id'=>21,'customer_ledger_id'=>11,'type'=>'PPR','type_id'=>8,
            'voucher_number'=>99,'project_head_subheads_id'=>101,
            'amount_in'=>'80.00','amount_out'=>'0.00','reference'=>'PPR-OLD',
            'detail'=>'Original receipt','date'=>'2026-11-18','is_active'=>1,
        ]);
        DB::table('booking_vouchers')->insert([
            'id'=>33,'project_id'=>1,'customer_ledger_id'=>11,'ledger_id'=>21,
            'voucher_series'=>'PPR','voucher_number'=>99,
            'customer_id'=>31,'plot_id'=>41,'slip_reference'=>'PPR-OLD',
            'receipt_date'=>'2026-11-18','description'=>'Original receipt',
            'amount'=>'80.00','is_active'=>1,
        ]);
    }

    protected function tearDown(): void
    {
        config(['database.default'=>$this->beforeConnection]);
        DB::purge('booking_cash_editor_test');
        parent::tearDown();
    }

    public function test_cash_ppr_correction_preserves_all_three_pks_and_has_no_reversal(): void
    {
        $editor=new BookingReceiptCashEditor();
        $fields=['date'=>'2026-11-19','reference'=>'PPR-CORRECT',
            'amount'=>'110.00','description'=>'Corrected receipt'];
        $editor->edit(1,11,$fields,7);
        self::assertSame(1,DB::table('customer_ledger')->count());
        self::assertSame(1,DB::table('ledgers')->count());
        self::assertSame(1,DB::table('booking_vouchers')->count());
        self::assertSame(110.0,(float)DB::table('customer_ledger')->where('id',11)->value('amount_out'));
        self::assertSame(110.0,(float)DB::table('ledgers')->where('id',21)->value('amount_in'));
        self::assertSame(110.0,(float)DB::table('booking_vouchers')->where('id',33)->value('amount'));
        self::assertSame(21,(int)DB::table('booking_vouchers')->where('id',33)->value('ledger_id'));
        self::assertSame(11,(int)DB::table('booking_vouchers')->where('id',33)->value('customer_ledger_id'));
        $editor->edit(1,11,$fields,7);
        self::assertSame(1,DB::table('ledgers')->count());
        self::assertSame(1,DB::table('booking_vouchers')->count());
    }

    public function test_cash_ppr_delete_archives_source_ledger_and_booking_without_rev_rows(): void
    {
        $editor=new BookingReceiptCashEditor();
        $editor->delete(1,11);
        foreach(['customer_ledger'=>11,'ledgers'=>21,'booking_vouchers'=>33] as $table=>$id) {
            self::assertSame(0,(int)DB::table($table)->where('id',$id)->value('is_active'));
            self::assertSame(1,DB::table($table)->count());
        }
        try {
            $editor->delete(1,11);
            self::fail('Repeat PPR Delete must not alter archived source.');
        }catch(\DomainException $e){
            self::assertStringContainsString('Only an active',$e->getMessage());
        }
    }

    public function test_balanced_cash_ppr_edit_preserves_receivable_fk_and_updates_both_ledger_legs(): void
    {
        DB::table('project_head_subheads')->insert(['id'=>102,'project_id'=>1]);
        DB::table('ledgers')->insert([
            'id'=>22,'customer_ledger_id'=>11,'type'=>'PPR','type_id'=>9,
            'voucher_number'=>99,'project_head_subheads_id'=>102,
            'amount_in'=>'0.00','amount_out'=>'80.00','reference'=>'PPR-OLD',
            'detail'=>'Original receipt','date'=>'2026-11-18','is_active'=>1,
        ]);
        (new BookingReceiptCashEditor())->edit(1,11,[
            'date'=>'2026-11-19','reference'=>'PPR-UPDATED',
            'amount'=>'110.00','description'=>'Balanced receipt edit',
        ],7);
        $this->assertEquals(110,DB::table('ledgers')->where('id',21)->value('amount_in'));
        $this->assertEquals(110,DB::table('ledgers')->where('id',22)->value('amount_out'));
        $this->assertEquals(110,DB::table('customer_ledger')->where('id',11)->value('amount_out'));
        $this->assertSame(21,(int)DB::table('booking_vouchers')->where('id',33)->value('ledger_id'));
        $this->assertSame(2,DB::table('ledgers')->where('customer_ledger_id',11)->count());
    }

    public function test_balanced_cash_ppr_delete_retires_both_legs_without_deleting_evidence(): void
    {
        DB::table('project_head_subheads')->insert(['id'=>102,'project_id'=>1]);
        DB::table('ledgers')->insert([
            'id'=>22,'customer_ledger_id'=>11,'type'=>'PPR','type_id'=>9,
            'voucher_number'=>99,'project_head_subheads_id'=>102,
            'amount_in'=>'0.00','amount_out'=>'80.00','reference'=>'PPR-OLD',
            'detail'=>'Original receipt','date'=>'2026-11-18','is_active'=>1,
        ]);
        (new BookingReceiptCashEditor())->delete(1,11);
        $this->assertSame(2,DB::table('ledgers')->where('customer_ledger_id',11)->count());
        $this->assertSame(2,DB::table('ledgers')
            ->where('customer_ledger_id',11)->where('is_active',0)->count());
        $this->assertSame(0,(int)DB::table('booking_vouchers')->where('id',33)->value('is_active'));
    }

    public function test_typed_booking_owner_refuses_legacy_ppr_edit_and_delete_without_mutation(): void
    {
        Schema::create('finance_posting_booking_sources',function(Blueprint $t){
            $t->unsignedBigInteger('booking_voucher_id');
            $t->unsignedBigInteger('posting_batch_id');
        });
        DB::table('finance_posting_booking_sources')->insert([
            'booking_voucher_id'=>33,'posting_batch_id'=>9001,
        ]);
        $editor=new BookingReceiptCashEditor();
        try {
            $editor->edit(1,11,[
                'date'=>'2026-11-19','reference'=>'PPR-NEW',
                'amount'=>'110.00','description'=>'Incorrect legacy correction',
            ],7);
            self::fail('Typed booking owner must refuse legacy PPR edit.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('typed booking',$e->getMessage());
        }
        try {
            $editor->delete(1,11);
            self::fail('Typed booking owner must refuse legacy PPR delete.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('typed booking',$e->getMessage());
        }
        self::assertSame(80.0,(float)DB::table('customer_ledger')
            ->where('id',11)->value('amount_out'));
        self::assertSame(1,(int)DB::table('booking_vouchers')
            ->where('id',33)->value('is_active'));
        self::assertSame(1,(int)DB::table('ledgers')
            ->where('id',21)->value('is_active'));
    }

    public function test_wrong_project_or_broken_fk_cannot_rewrite_ppr(): void
    {
        $editor=new BookingReceiptCashEditor();
        $change=['date'=>'2026-11-19','reference'=>'PPR-CORRECT',
            'amount'=>'110.00','description'=>'Corrected'];
        try {
            $editor->edit(2,11,$change,7);
            self::fail('Foreign project PPR edit must fail.');
        }catch(\DomainException|\Illuminate\Database\RecordNotFoundException $e){
            self::assertNotEmpty($e->getMessage());
        }
        DB::table('booking_vouchers')->where('id',33)->update(['ledger_id'=>999]);
        try {
            $editor->edit(1,11,$change,7);
            self::fail('Broken BookingVoucher ledger FK must fail.');
        }catch(\DomainException $e) {
            self::assertStringContainsString('FK',$e->getMessage());
        }
        self::assertSame(80.0,(float)DB::table('customer_ledger')->where('id',11)->value('amount_out'));
    }
}
