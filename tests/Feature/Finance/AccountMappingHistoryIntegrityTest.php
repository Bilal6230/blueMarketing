<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\AccountingController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountMappingHistoryIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->app->instance('request', Request::create('/', 'GET', [], ['selected_action' => 5]));
        Schema::dropAllTables();
        Schema::create('project_head_subheads', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('head_accounting_id'); $t->unsignedBigInteger('subhead_accounting_id');
            $t->timestamps(); $t->softDeletes();
        });
        DB::table('project_head_subheads')->insert([
            'id' => 1, 'project_id' => 5, 'head_accounting_id' => 10, 'subhead_accounting_id' => 20,
        ]);
    }

    public static function sources(): array
    {
        return [
            ['ledgers', 'project_head_subheads_id'], ['draft_ledgers', 'project_head_subheads_id'],
            ['journal_voucher_details', 'account_id'], ['commision_voucher_details', 'account_id'],
            ['finance_posting_lines', 'project_head_subhead_id'],
        ];
    }

    #[DataProvider('sources')]
    public function test_even_archived_financial_references_prevent_delete_and_reassignment(string $table, string $column): void
    {
        Schema::create($table, function (Blueprint $t) use ($column): void {
            $t->id(); $t->unsignedBigInteger($column); $t->softDeletes();
        });
        DB::table($table)->insert(['id' => 1, $column => 1, 'deleted_at' => now()]);
        $controller = new AccountingController();
        $this->assertSame(409, $controller->category_delete(1)->getStatusCode());
        $request = Request::create('/', 'PUT', ['head_ids' => [30]]);
        $this->assertSame(409, $controller->category_update($request, 1)->getStatusCode());
        $this->assertDatabaseHas('project_head_subheads', [
            'id' => 1, 'head_accounting_id' => 10, 'deleted_at' => null,
        ]);
        $this->assertDatabaseHas($table, ['id' => 1, $column => 1]);
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_unused_mapping_soft_deletes_and_cannot_be_edited_after_archival(): void
    {
        $controller = new AccountingController();
        $this->assertSame(200, $controller->category_delete(1)->getStatusCode());
        $this->assertSoftDeleted('project_head_subheads', ['id' => 1]);
        $this->assertSame(0, \App\Models\ProjectHeadSubhead::count());
        $this->assertSame(404, $controller->category_delete(1)->getStatusCode());
        $controller->category_update(Request::create('/', 'PUT', ['head_ids' => [30]]), 1);
        $this->assertDatabaseHas('project_head_subheads', ['id' => 1, 'head_accounting_id' => 10]);
    }

    public function test_foreign_project_mapping_cannot_be_deleted_or_reassigned(): void
    {
        DB::table('project_head_subheads')->where('id', 1)->update(['project_id' => 6]);
        $controller = new AccountingController();
        $this->assertSame(404, $controller->category_delete(1)->getStatusCode());
        $controller->category_update(Request::create('/', 'PUT', ['head_ids' => [30]]), 1);
        $this->assertDatabaseHas('project_head_subheads', [
            'id' => 1, 'project_id' => 6, 'head_accounting_id' => 10, 'deleted_at' => null,
        ]);
    }
}
