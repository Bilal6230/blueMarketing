<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\ContractorController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ContractorDefinitionIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::dropAllTables();
        Schema::create('projects', fn (Blueprint $table) => $table->id());
        Schema::create('head_accountings', function (Blueprint $table): void { $table->id(); $table->boolean('is_active'); });
        Schema::create('subhead_accountings', function (Blueprint $table): void { $table->id(); $table->boolean('is_active'); });
        Schema::create('project_head_subheads', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('head_accounting_id'); $table->unsignedBigInteger('subhead_accounting_id');
            $table->timestamps(); $table->softDeletes();
            $table->unique(['id', 'project_id'], 'project_head_subheads_id_project_unique');
        });
        (require database_path('migrations/2026_11_03_000013_create_project_contractors.php'))->up();
        DB::table('projects')->insert([['id' => 5], ['id' => 6]]);
        DB::table('head_accountings')->insert(['id' => 1, 'is_active' => 1]);
        DB::table('subhead_accountings')->insert(['id' => 1, 'is_active' => 1]);
        DB::table('project_head_subheads')->insert([
            ['id' => 11, 'project_id' => 5, 'head_accounting_id' => 1, 'subhead_accounting_id' => 1],
            ['id' => 12, 'project_id' => 6, 'head_accounting_id' => 1, 'subhead_accounting_id' => 1],
        ]);
    }

    public function test_duplicate_code_is_rejected_without_changing_existing_contractor(): void
    {
        DB::table('contractors')->insert(['project_id' => 5, 'code' => 'CON-1', 'name' => 'Existing', 'project_head_subhead_id' => 11]);
        $request = Request::create('/', 'POST', ['code' => 'CON-1', 'name' => 'Other', 'project_head_subhead_id' => 11], ['selected_action' => 5]);
        $this->app->instance('request', $request);
        try {
            (new ContractorController())->store($request);
            $this->fail('Duplicate contractor code was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('contractors', 1);
        $this->assertDatabaseHas('contractors', ['project_id' => 5, 'code' => 'CON-1', 'name' => 'Existing']);
    }

    public function test_foreign_project_account_cannot_be_assigned(): void
    {
        $request = Request::create('/', 'POST', ['code' => 'CON-2', 'name' => 'Other', 'project_head_subhead_id' => 12], ['selected_action' => 5]);
        $this->app->instance('request', $request);
        try {
            (new ContractorController())->store($request);
            $this->fail('Foreign project account was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('contractors', 0);
    }

    public function test_contractor_ledger_uses_account_mapping_project_when_ledger_has_no_project_column(): void
    {
        Schema::create('journal_vouchers', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('project_id'); $table->unsignedBigInteger('contractor_id')->nullable();
        });
        Schema::create('customer_ledger', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('project_id'); $table->unsignedBigInteger('contractor_id')->nullable();
        });
        Schema::create('ledgers', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('project_head_subheads_id');
            $table->unsignedBigInteger('type_id')->nullable(); $table->unsignedBigInteger('customer_ledger_id')->nullable();
            $table->string('type'); $table->string('voucher_number'); $table->text('detail')->nullable();
            $table->decimal('amount_in', 14, 2); $table->decimal('amount_out', 14, 2);
            $table->boolean('is_active'); $table->date('date');
        });
        DB::table('contractors')->insert(['id' => 20, 'project_id' => 5, 'code' => 'CON-5', 'name' => 'Builder', 'project_head_subhead_id' => 11]);
        DB::table('journal_vouchers')->insert(['id' => 40, 'project_id' => 5, 'contractor_id' => 20]);
        DB::table('ledgers')->insert([
            ['project_head_subheads_id' => 11, 'type_id' => 40, 'type' => 'JV', 'voucher_number' => '10', 'amount_in' => '20.00', 'amount_out' => '0.00', 'is_active' => 1, 'date' => '2026-11-01'],
            ['project_head_subheads_id' => 12, 'type_id' => 40, 'type' => 'JV', 'voucher_number' => '10', 'amount_in' => '999.00', 'amount_out' => '0.00', 'is_active' => 1, 'date' => '2026-11-01'],
        ]);
        $this->app->instance('request', Request::create('/', 'GET', [], ['selected_action' => 5]));

        $view = (new ContractorController())->ledger(20);
        $this->assertCount(1, $view->getData()['entries']);
        $this->assertSame(2000, $view->getData()['balance']);
    }
}
