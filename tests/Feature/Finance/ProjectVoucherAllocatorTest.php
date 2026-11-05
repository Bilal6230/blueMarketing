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
}
