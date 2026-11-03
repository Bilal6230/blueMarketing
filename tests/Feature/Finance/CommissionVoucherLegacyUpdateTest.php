<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class CommissionVoucherLegacyUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropAllTables();
        Schema::create('users', function (Blueprint $t): void { $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('password')->nullable(); $t->integer('project_id'); $t->integer('status_id')->default(1); $t->timestamps(); $t->softDeletes(); });
        Schema::create('project_head_subheads', function (Blueprint $t): void { $t->id(); $t->integer('project_id'); $t->integer('head_accounting_id'); $t->integer('subhead_accounting_id'); $t->timestamps(); $t->softDeletes(); });
        Schema::create('commision_vouchers', function (Blueprint $t): void { $t->id(); $t->integer('project_id'); $t->string('voucher_number'); $t->string('reference')->nullable(); $t->text('description')->nullable(); $t->date('date'); $t->decimal('total_debit',15,2); $t->decimal('total_credit',15,2); $t->integer('created_by'); $t->integer('updated_by')->nullable(); $t->string('status')->default('pending'); $t->timestamps(); $t->softDeletes(); });
        Schema::create('commision_voucher_details', function (Blueprint $t): void { $t->id(); $t->integer('commision_voucher_id'); $t->integer('account_id'); $t->decimal('debit',15,2); $t->decimal('credit',15,2); $t->text('description')->nullable(); $t->timestamps(); $t->softDeletes(); });
        Schema::create('ledgers', function (Blueprint $t): void { $t->id(); $t->string('type'); $t->integer('type_id'); $t->string('voucher_number')->nullable(); $t->integer('project_head_subheads_id'); $t->integer('customer_ledger_id')->nullable(); $t->string('reference')->nullable(); $t->decimal('amount_in',15,2); $t->decimal('amount_out',15,2); $t->text('detail')->nullable(); $t->integer('create_by'); $t->integer('update_by')->nullable(); $t->integer('is_active'); $t->string('status'); $t->date('date'); $t->timestamps(); });
        DB::table('users')->insert(['id'=>1,'name'=>'Admin','project_id'=>1,'status_id'=>1,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('project_head_subheads')->insert([['id'=>11,'project_id'=>1,'head_accounting_id'=>1,'subhead_accounting_id'=>1,'created_at'=>now(),'updated_at'=>now()],['id'=>12,'project_id'=>1,'head_accounting_id'=>2,'subhead_accounting_id'=>2,'created_at'=>now(),'updated_at'=>now()],['id'=>21,'project_id'=>2,'head_accounting_id'=>1,'subhead_accounting_id'=>1,'created_at'=>now(),'updated_at'=>now()]]);
        DB::table('commision_vouchers')->insert(['id'=>7,'project_id'=>1,'voucher_number'=>'19','description'=>'Old CV','date'=>'2026-10-01','total_debit'=>100,'total_credit'=>100,'created_by'=>1,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('commision_voucher_details')->insert([['commision_voucher_id'=>7,'account_id'=>11,'debit'=>100,'credit'=>0,'description'=>'Old debit','created_at'=>now(),'updated_at'=>now()],['commision_voucher_id'=>7,'account_id'=>12,'debit'=>0,'credit'=>100,'description'=>'Old credit','created_at'=>now(),'updated_at'=>now()]]);
        DB::table('ledgers')->insert([['type'=>'CV','type_id'=>7,'voucher_number'=>'19','project_head_subheads_id'=>11,'amount_in'=>100,'amount_out'=>0,'detail'=>'Old debit','create_by'=>1,'is_active'=>1,'status'=>'0','date'=>'2026-10-01','created_at'=>now(),'updated_at'=>now()],['type'=>'CV','type_id'=>7,'voucher_number'=>'19','project_head_subheads_id'=>12,'amount_in'=>0,'amount_out'=>100,'detail'=>'Old credit','create_by'=>1,'is_active'=>1,'status'=>'0','date'=>'2026-10-01','created_at'=>now(),'updated_at'=>now()],['type'=>'JV','type_id'=>7,'voucher_number'=>'77','project_head_subheads_id'=>11,'amount_in'=>25,'amount_out'=>0,'detail'=>'Colliding JV','create_by'=>1,'is_active'=>1,'status'=>'0','date'=>'2026-10-01','created_at'=>now(),'updated_at'=>now()]]);
        config(['finance.v2_pilot_api_enabled'=>false,'finance.v2_pilot_write_enabled'=>false]);
        $this->actingAs(User::findOrFail(1))->withoutMiddleware();
    }

    public function test_update_reverses_only_its_own_cv_and_posts_corrected_lines(): void
    {
        $this->withUnencryptedCookie('selected_action','1')->put(route('commision.voucher.update',7), [
            'date'=>'2026-11-03','reference'=>'CV-CORRECTED','description'=>'Corrected CV',
            'accounts'=>[1,2],'sub_accounts'=>[11,12],
            'debit'=>['175.00','0'],'credit'=>['0','175.00'],
            'line_description'=>['New debit','New credit'],
        ])->assertRedirect(route('commision.voucher.edit',['id'=>7]));

        $this->assertDatabaseHas('commision_vouchers',['id'=>7,'project_id'=>1,'total_debit'=>175,'total_credit'=>175,'reference'=>'CV-CORRECTED']);
        $this->assertSame(2, DB::table('commision_voucher_details')->where('commision_voucher_id',7)->whereNull('deleted_at')->count());
        $this->assertSame(2, DB::table('commision_voucher_details')->where('commision_voucher_id',7)->whereNotNull('deleted_at')->count());
        $this->assertSame(6, DB::table('ledgers')->where('type','CV')->where('type_id',7)->count());
        $this->assertEquals(175.0, (float) DB::table('ledgers')->where('type','CV')->where('type_id',7)->where('project_head_subheads_id',11)->selectRaw('SUM(amount_in-amount_out) balance')->value('balance'));
        $this->assertEquals(-175.0, (float) DB::table('ledgers')->where('type','CV')->where('type_id',7)->where('project_head_subheads_id',12)->selectRaw('SUM(amount_in-amount_out) balance')->value('balance'));
        $this->assertSame(1, DB::table('ledgers')->where('type','JV')->where('type_id',7)->count());
        $this->assertDatabaseHas('ledgers',['type'=>'JV','type_id'=>7,'detail'=>'Colliding JV','amount_in'=>25]);
    }

    public function test_invalid_or_foreign_lines_make_no_changes(): void
    {
        $this->withUnencryptedCookie('selected_action','1')->put(route('commision.voucher.update',7), [
            'date'=>'2026-11-03','description'=>'Bad CV','accounts'=>[1,2],
            'sub_accounts'=>[11,21],'debit'=>['175.00','0'],'credit'=>['0','175.00'],
        ])->assertSessionHasErrors('sub_accounts');
        $this->assertSame(2, DB::table('ledgers')->where('type','CV')->where('type_id',7)->count());
        $this->assertDatabaseHas('commision_vouchers',['id'=>7,'total_debit'=>100,'description'=>'Old CV']);
    }

    public function test_delete_reverses_cv_without_touching_colliding_jv(): void
    {
        $this->withUnencryptedCookie('selected_action','1')->delete(route('commision.voucher.delete',7), [
            'effective_date'=>'2026-11-03','reason'=>'Cancelled commission',
        ])->assertRedirect(route('commision.voucher.index'));
        $this->assertNotNull(DB::table('commision_vouchers')->where('id',7)->value('deleted_at'));
        $this->assertSame(4, DB::table('ledgers')->where('type','CV')->where('type_id',7)->count());
        $this->assertSame(1, DB::table('ledgers')->where('type','JV')->where('type_id',7)->count());
        $this->assertDatabaseHas('ledgers',['type'=>'JV','type_id'=>7,'detail'=>'Colliding JV','amount_in'=>25]);
    }
}
