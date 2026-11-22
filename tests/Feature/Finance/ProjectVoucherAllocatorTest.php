<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\ProjectVoucherAllocator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectVoucherAllocatorTest extends TestCase
{
    public function test_allocations_remain_project_scoped_and_never_reuse_retained_numbers(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        Schema::create('projects', fn (Blueprint $table) => $table->id());
        Schema::create('customer_ledger', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('project_id');
            $table->string('transaction_type'); $table->unsignedBigInteger('type_id');
        });
        Schema::create('project_head_subheads', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('project_id');
        });
        Schema::create('ledgers', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('project_head_subheads_id');
            $table->string('type'); $table->unsignedBigInteger('voucher_number');
        });
        Schema::create('ledger_voucher_counters', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('project_id');
            $table->string('voucher_type'); $table->unsignedBigInteger('last_number');
            $table->timestamps(); $table->unique(['project_id', 'voucher_type']);
        });
        DB::table('projects')->insert([['id' => 5], ['id' => 6]]);
        DB::table('project_head_subheads')->insert([['id' => 11, 'project_id' => 5], ['id' => 12, 'project_id' => 6]]);
        DB::table('customer_ledger')->insert([['project_id' => 5, 'transaction_type' => 'CR', 'type_id' => 9], ['project_id' => 6, 'transaction_type' => 'CR', 'type_id' => 500]]);
        DB::table('ledgers')->insert([['project_head_subheads_id' => 11, 'type' => 'CR', 'voucher_number' => 12], ['project_head_subheads_id' => 12, 'type' => 'CR', 'voucher_number' => 900]]);

        $allocator = new ProjectVoucherAllocator();
        $numbers = DB::transaction(fn () => [$allocator->next(5, 'CR'), $allocator->next(5, 'CR')]);
        $this->assertSame([13, 14], $numbers);
        $this->assertDatabaseHas('ledger_voucher_counters', ['project_id' => 5, 'voucher_type' => 'CR', 'last_number' => 14]);
        $this->assertDatabaseMissing('ledger_voucher_counters', ['project_id' => 6, 'voucher_type' => 'CR']);
        DB::table('customer_ledger')->insert(['project_id' => 5, 'transaction_type' => 'CP', 'type_id' => 41]);
        request()->cookies->set('selected_action', '5');
        $this->assertSame(42, getVocuherNumber('CP'));
    }

    public function test_independent_bank_journal_and_receipt_series_ignore_legacy_id_jumps(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        Schema::create('projects', fn(Blueprint $t) => $t->id());
        Schema::create('customer_ledger', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('transaction_type'); $t->unsignedBigInteger('type_id');
        });
        Schema::create('project_head_subheads', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
        });
        Schema::create('ledgers', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_head_subheads_id');
            $t->string('type'); $t->unsignedBigInteger('voucher_number');
        });
        Schema::create('ledger_voucher_counters', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('voucher_type'); $t->unsignedBigInteger('last_number');
            $t->timestamps(); $t->unique(['project_id','voucher_type']);
        });
        Schema::create('project_voucher_display_numbers', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('series'); $t->string('display_number');
        });
        Schema::create('journal_vouchers', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('type'); $t->unsignedBigInteger('voucher_number');
        });
        Schema::create('booking_vouchers', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->string('voucher_series'); $t->unsignedBigInteger('voucher_number');
        });
        Schema::create('finance_plot_receipts', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('project_id'); $t->string('receipt_number');
        });
        DB::table('projects')->insert(['id'=>3]);
        DB::table('project_head_subheads')->insert(['id'=>7,'project_id'=>3]);
        DB::table('customer_ledger')->insert([
            ['project_id'=>3,'transaction_type'=>'BOR','type_id'=>843],
            ['project_id'=>3,'transaction_type'=>'BOP','type_id'=>2788],
        ]);
        DB::table('ledgers')->insert([
            ['project_head_subheads_id'=>7,'type'=>'BOR','voucher_number'=>843],
            ['project_head_subheads_id'=>7,'type'=>'BOP','voucher_number'=>2788],
            ['project_head_subheads_id'=>7,'type'=>'JV','voucher_number'=>651],
            ['project_head_subheads_id'=>7,'type'=>'PPR','voucher_number'=>1648],
        ]);
        DB::table('project_voucher_display_numbers')->insert([
            ['project_id'=>3,'series'=>'BOR','display_number'=>'BOR-5'],
            ['project_id'=>3,'series'=>'BOP','display_number'=>'BOP-1'],
            ['project_id'=>3,'series'=>'JV','display_number'=>'JV-652'],
        ]);
        DB::table('journal_vouchers')->insert([
            ['project_id'=>3,'type'=>'JV','voucher_number'=>126],
            ['project_id'=>3,'type'=>'JV','voucher_number'=>127],
            ['project_id'=>3,'type'=>'JV','voucher_number'=>652],
        ]);
        DB::table('booking_vouchers')->insert([
            ['project_id'=>3,'voucher_series'=>'PPR','voucher_number'=>53],
        ]);
        DB::table('finance_plot_receipts')->insert([
            ['project_id'=>3,'receipt_number'=>'PPR-1649'],
        ]);
        DB::table('ledger_voucher_counters')->insert([
            ['project_id'=>3,'voucher_type'=>'JV','last_number'=>652],
            ['project_id'=>3,'voucher_type'=>'PPR','last_number'=>1649],
        ]);
        $allocator = new ProjectVoucherAllocator();
        $this->assertSame(6, $allocator->preview(3,'BOR'));
        $this->assertSame(2, $allocator->preview(3,'BOP'));
        $this->assertSame(128, $allocator->preview(3,'JV'));
        $this->assertSame(54, $allocator->preview(3,'PPR'));
        $this->assertSame([6,2,128,54], DB::transaction(fn() => [
            $allocator->next(3,'BOR'),$allocator->next(3,'BOP'),
            $allocator->next(3,'JV'),$allocator->next(3,'PPR'),
        ]));
    }

}
