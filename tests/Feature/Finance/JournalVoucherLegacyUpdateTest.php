<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class JournalVoucherLegacyUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropAllTables();
        Schema::create('users', function (Blueprint $t): void { $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('password')->nullable(); $t->integer('project_id'); $t->integer('status_id')->default(1); $t->timestamps(); $t->softDeletes(); });
        Schema::create('project_head_subheads', function (Blueprint $t): void { $t->id(); $t->integer('project_id'); $t->integer('head_accounting_id'); $t->integer('subhead_accounting_id'); $t->timestamps(); $t->softDeletes(); });
        Schema::create('journal_vouchers', function (Blueprint $t): void { $t->id(); $t->integer('project_id'); $t->string('type')->nullable(); $t->integer('voucher_number'); $t->string('reference')->nullable(); $t->text('description'); $t->date('date'); $t->decimal('total_debit',15,2); $t->decimal('total_credit',15,2); $t->integer('created_by'); $t->integer('updated_by')->nullable(); $t->string('status')->default('pending'); $t->timestamps(); $t->softDeletes(); });
        Schema::create('journal_voucher_details', function (Blueprint $t): void { $t->id(); $t->integer('journal_voucher_id'); $t->integer('account_id'); $t->decimal('debit',15,2); $t->decimal('credit',15,2); $t->text('description')->nullable(); $t->timestamps(); $t->softDeletes(); });
        Schema::create('ledgers', function (Blueprint $t): void { $t->id(); $t->string('type'); $t->integer('type_id'); $t->integer('voucher_number'); $t->integer('project_head_subheads_id'); $t->integer('journal_voucher_detail_id')->nullable()->unique(); $t->string('reference')->nullable(); $t->decimal('amount_in',15,2); $t->decimal('amount_out',15,2); $t->text('detail')->nullable(); $t->integer('create_by'); $t->integer('update_by')->nullable(); $t->integer('is_active'); $t->string('status'); $t->date('date'); $t->timestamps(); });
        DB::table('users')->insert(['id'=>1,'name'=>'Admin','project_id'=>1,'status_id'=>1,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('project_head_subheads')->insert([['id'=>11,'project_id'=>1,'head_accounting_id'=>1,'subhead_accounting_id'=>1,'created_at'=>now(),'updated_at'=>now()],['id'=>12,'project_id'=>1,'head_accounting_id'=>2,'subhead_accounting_id'=>2,'created_at'=>now(),'updated_at'=>now()]]);
        DB::table('journal_vouchers')->insert(['id'=>7,'project_id'=>1,'type'=>'JV','voucher_number'=>9,'description'=>'Old','date'=>'2026-10-01','total_debit'=>100,'total_credit'=>100,'created_by'=>1,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('journal_voucher_details')->insert([['id'=>21,'journal_voucher_id'=>7,'account_id'=>11,'debit'=>100,'credit'=>0,'description'=>'Old debit','created_at'=>now(),'updated_at'=>now()],['id'=>22,'journal_voucher_id'=>7,'account_id'=>12,'debit'=>0,'credit'=>100,'description'=>'Old credit','created_at'=>now(),'updated_at'=>now()]]);
        DB::table('ledgers')->insert([['type'=>'JV','type_id'=>7,'voucher_number'=>9,'project_head_subheads_id'=>11,'journal_voucher_detail_id'=>21,'amount_in'=>100,'amount_out'=>0,'detail'=>'Old debit','create_by'=>1,'is_active'=>1,'status'=>'0','date'=>'2026-10-01','created_at'=>now(),'updated_at'=>now()],['type'=>'JV','type_id'=>7,'voucher_number'=>9,'project_head_subheads_id'=>12,'journal_voucher_detail_id'=>22,'amount_in'=>0,'amount_out'=>100,'detail'=>'Old credit','create_by'=>1,'is_active'=>1,'status'=>'0','date'=>'2026-10-01','created_at'=>now(),'updated_at'=>now()]]);
        config(['finance.v2_pilot_api_enabled'=>false,'finance.v2_pilot_write_enabled'=>false]);
        $this->actingAs(User::findOrFail(1))->withoutMiddleware();
    }

    public function test_update_appends_reversal_and_corrected_balanced_legs(): void
    {
        $response = $this->withUnencryptedCookie('selected_action','1')->put(route('journal.voucher.update',7), [
            'date'=>'2026-11-02','reference'=>'UPDATED','description'=>'Corrected JV',
            'accounts'=>[1,2],'sub_accounts'=>[11,12],
            'debit'=>['150.00','0'],'credit'=>['0','150.00'],
            'line_description'=>['New debit','New credit'],
        ]);
        $response->assertRedirect(route('journal.voucher.edit',['id'=>7]));
        $this->assertDatabaseHas('journal_vouchers',['id'=>7,'total_debit'=>150,'total_credit'=>150,'reference'=>'UPDATED']);
        $this->assertSame(2, DB::table('journal_voucher_details')->where('journal_voucher_id',7)->whereNull('deleted_at')->count());
        $this->assertSame(2, DB::table('journal_voucher_details')->where('journal_voucher_id',7)->whereNotNull('deleted_at')->count());
        $this->assertSame(6, DB::table('ledgers')->where('type','JV')->where('type_id',7)->count());
        $this->assertEquals(350.0, (float) DB::table('ledgers')->where('type_id',7)->sum('amount_in'));
        $this->assertEquals(350.0, (float) DB::table('ledgers')->where('type_id',7)->sum('amount_out'));
        $this->assertEquals(150.0, (float) DB::table('ledgers')->where('type_id',7)->where('project_head_subheads_id',11)->selectRaw('SUM(amount_in-amount_out) balance')->value('balance'));
        $this->assertEquals(-150.0, (float) DB::table('ledgers')->where('type_id',7)->where('project_head_subheads_id',12)->selectRaw('SUM(amount_in-amount_out) balance')->value('balance'));
        $this->assertDatabaseHas('ledgers',['type_id'=>7,'project_head_subheads_id'=>11,'amount_in'=>150,'amount_out'=>0,'is_active'=>1]);
        $this->assertDatabaseHas('ledgers',['type_id'=>7,'project_head_subheads_id'=>12,'amount_in'=>0,'amount_out'=>150,'is_active'=>1]);
    }

    public function test_unbalanced_update_is_rejected_without_changing_any_entry(): void
    {
        $this->withUnencryptedCookie('selected_action','1')->put(route('journal.voucher.update',7), [
            'date'=>'2026-11-02','description'=>'Bad JV','accounts'=>[1,2],'sub_accounts'=>[11,12],
            'debit'=>['150.00','0'],'credit'=>['0','140.00'],'line_description'=>['Debit','Credit'],
        ])->assertSessionHasErrors('debit');
        $this->assertSame(2, DB::table('ledgers')->where('type_id',7)->count());
        $this->assertSame(2, DB::table('journal_voucher_details')->whereNull('deleted_at')->count());
        $this->assertDatabaseHas('journal_vouchers',['id'=>7,'total_debit'=>100,'total_credit'=>100,'description'=>'Old']);
    }
}
