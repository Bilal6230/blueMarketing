<?php

namespace Tests\Feature\Finance;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LabourProjectMigrationPreflightTest extends TestCase
{
    public function test_unscoped_labour_master_is_rejected_before_project_foreign_key(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        Schema::create('labours', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('project_id')->nullable();
        });
        Schema::create('labour_ledgers', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('labour_id'); $table->unsignedBigInteger('project_id');
        });
        Schema::create('contractors', fn (Blueprint $table) => $table->id());
        Schema::create('customer_ledger', fn (Blueprint $table) => $table->id());
        DB::table('labours')->insert(['id' => 1, 'project_id' => null]);
        DB::table('labour_ledgers')->insert(['id' => 8, 'labour_id' => 1, 'project_id' => 5]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('a labour master is missing, unscoped, or belongs to another project');
        (require database_path('migrations/2026_11_03_000014_scope_labour_ledgers_and_link_contractors.php'))->up();
    }
}
