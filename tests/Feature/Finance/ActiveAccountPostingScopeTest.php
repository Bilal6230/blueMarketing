<?php

namespace Tests\Feature\Finance;

use App\Models\ProjectHeadSubhead;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActiveAccountPostingScopeTest extends TestCase
{
    public function test_inactive_definitions_are_hidden_from_posting_but_history_remains_visible(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        Schema::create('head_accountings', function (Blueprint $t): void { $t->id(); $t->boolean('is_active'); });
        Schema::create('subhead_accountings', function (Blueprint $t): void { $t->id(); $t->boolean('is_active'); });
        Schema::create('project_head_subheads', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('head_accounting_id'); $t->unsignedBigInteger('subhead_accounting_id');
            $t->timestamps(); $t->softDeletes();
        });
        DB::table('head_accountings')->insert([['id' => 1, 'is_active' => 1], ['id' => 2, 'is_active' => 0]]);
        DB::table('subhead_accountings')->insert([['id' => 1, 'is_active' => 1], ['id' => 2, 'is_active' => 0]]);
        DB::table('project_head_subheads')->insert([
            ['id' => 10, 'project_id' => 5, 'head_accounting_id' => 1, 'subhead_accounting_id' => 1],
            ['id' => 20, 'project_id' => 5, 'head_accounting_id' => 2, 'subhead_accounting_id' => 1],
            ['id' => 30, 'project_id' => 5, 'head_accounting_id' => 1, 'subhead_accounting_id' => 2],
        ]);

        $this->assertSame([10], ProjectHeadSubhead::availableForPosting()->where('project_id', 5)->pluck('id')->all());
        $this->assertSame([10, 20, 30], ProjectHeadSubhead::where('project_id', 5)->orderBy('id')->pluck('id')->all());
        DB::table('head_accountings')->where('id', 2)->update(['is_active' => 1]);
        DB::table('subhead_accountings')->where('id', 2)->update(['is_active' => 1]);
        $this->assertSame([10, 20, 30], ProjectHeadSubhead::availableForPosting()->where('project_id', 5)->orderBy('id')->pluck('id')->all());
    }
}
