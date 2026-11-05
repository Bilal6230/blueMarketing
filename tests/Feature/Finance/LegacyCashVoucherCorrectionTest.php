<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\LegacyCashVoucherCorrection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class LegacyCashVoucherCorrectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); Schema::dropAllTables();
        Schema::create('projects', function (Blueprint $t) { $t->id(); });
        Schema::create('head_accountings', function (Blueprint $t) { $t->id(); $t->integer('is_active')->default(1); });
        Schema::create('subhead_accountings', function (Blueprint $t) { $t->id(); $t->integer('is_active')->default(1); });
        Schema::create('project_head_subheads', function (Blueprint $t) { $t->id(); $t->integer('project_id'); $t->integer('head_accounting_id'); $t->integer('subhead_accounting_id'); $t->integer('customer_id')->nullable(); $t->integer('plot_id')->nullable(); $t->timestamps(); $t->softDeletes(); });
        Schema::create('customer_ledger', function (Blueprint $t) { $t->id(); $t->integer('customer_id')->nullable(); $t->integer('project_id'); $t->integer('plot_id')->nullable(); $t->integer('type_id'); $t->string('reference'); $t->integer('payment_type'); $t->string('t_number')->nullable(); $t->integer('bank_id')->nullable(); $t->date('passing_date')->nullable(); $t->integer('passing_status')->default(0); $t->json('check_history')->nullable(); $t->date('date'); $t->string('transaction_type'); $t->decimal('amount_in',15,2); $t->decimal('amount_out',15,2); $t->text('description')->nullable(); $t->integer('is_active'); $t->integer('is_approve')->default(1); $t->text('note')->nullable(); $t->dateTime('bank_post_at')->nullable(); $t->text('delete_reason')->nullable(); $t->timestamps(); });
        Schema::create('ledgers', function (Blueprint $t) { $t->id(); $t->string('type'); $t->integer('type_id'); $t->integer('voucher_number'); $t->integer('project_head_subheads_id'); $t->integer('customer_ledger_id')->nullable(); $t->string('reference')->nullable(); $t->decimal('amount_in',15,2); $t->decimal('amount_out',15,2); $t->text('detail')->nullable(); $t->integer('create_by'); $t->integer('update_by')->nullable(); $t->integer('is_active'); $t->string('status'); $t->date('date'); $t->timestamps(); });
        Schema::create('project_voucher_fiscal_sequences', function (Blueprint $t) { $t->id(); $t->integer('project_id'); $t->string('series'); $t->integer('financial_year'); $t->integer('last_number'); $t->timestamps(); $t->unique(['project_id','series','financial_year']); });
        Schema::create('ledger_voucher_counters', function (Blueprint $t) { $t->id(); $t->integer('project_id'); $t->string('voucher_type'); $t->integer('last_number'); $t->timestamps(); $t->unique(['project_id','voucher_type']); });
        Schema::create('project_voucher_display_settings', function (Blueprint $t) { $t->id(); $t->integer('project_id'); $t->string('series'); $t->string('prefix'); });
        Schema::create('project_voucher_display_numbers', function (Blueprint $t) { $t->id(); $t->integer('project_id'); $t->string('source_kind'); $t->integer('source_id'); $t->string('series'); $t->integer('financial_year'); $t->string('display_number'); $t->timestamp('created_at')->nullable(); $t->unique(['project_id','source_kind','source_id']); $t->unique(['project_id','display_number']); });
        DB::table('projects')->insert([['id'=>1],['id'=>2]]);
        DB::table('head_accountings')->insert(['id'=>1,'is_active'=>1]);
        DB::table('subhead_accountings')->insert(['id'=>1,'is_active'=>1]);
        DB::table('project_head_subheads')->insert([['id'=>11,'project_id'=>1,'head_accounting_id'=>1,'subhead_accounting_id'=>1,'customer_id'=>5,'created_at'=>now(),'updated_at'=>now()],['id'=>21,'project_id'=>2,'head_accounting_id'=>1,'subhead_accounting_id'=>1,'customer_id'=>null,'created_at'=>now(),'updated_at'=>now()]]);
        DB::table('customer_ledger')->insert(['id'=>7,'customer_id'=>5,'project_id'=>1,'type_id'=>9,'reference'=>'CR-9','payment_type'=>1,'date'=>'2026-10-01','transaction_type'=>'CR','amount_in'=>100,'amount_out'=>0,'description'=>'Old','is_active'=>1,'is_approve'=>1,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('ledgers')->insert(['id'=>8,'type'=>'CR','type_id'=>9,'voucher_number'=>9,'project_head_subheads_id'=>11,'customer_ledger_id'=>7,'reference'=>'CR-9','amount_in'=>100,'amount_out'=>0,'detail'=>'Old','create_by'=>1,'is_active'=>1,'status'=>'0','date'=>'2026-10-01','created_at'=>now(),'updated_at'=>now()]);
    }

    public function test_it_preserves_original_and_posts_reversal_plus_correction(): void
    {
        app(LegacyCashVoucherCorrection::class)->correct(1,7,11,[
            'reference'=>'CR-9-CORR','date'=>'2026-11-03','description'=>'Corrected','amount_in'=>'125.00','amount_out'=>0,
            'payment_type'=>1,'t_number'=>null,'bank_id'=>null,'passing_date'=>null,'passing_status'=>0,
        ],1);
        $this->assertDatabaseHas('customer_ledger',['id'=>7,'amount_in'=>100,'reference'=>'CR-9']);
        $this->assertSame(3, DB::table('customer_ledger')->count());
        $this->assertSame(3, DB::table('ledgers')->count());
        $this->assertEquals(125.0, (float) DB::table('ledgers')->selectRaw('SUM(amount_in-amount_out) balance')->value('balance'));
        $this->assertDatabaseHas('ledgers',['reference'=>'CR-9-CORR','amount_in'=>125,'amount_out'=>0,'project_head_subheads_id'=>11]);
    }

    public function test_foreign_project_account_rolls_back_everything(): void
    {
        try { app(LegacyCashVoucherCorrection::class)->correct(1,7,21,['date'=>'2026-11-03'],1); } catch (\Throwable) {}
        $this->assertSame(1, DB::table('customer_ledger')->count());
        $this->assertSame(1, DB::table('ledgers')->count());
    }
}
