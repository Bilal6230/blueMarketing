<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\Finance\VoucherController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CashInDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    public function test_cash_in_data_includes_only_cr_for_selected_project(): void
    {
        $selectedProjectId = DB::table('projects')->insertGetId([
            'project' => 'Selected Project',
            'address' => 'Address 1',
            'is_active' => 1,
            'create_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherProjectId = DB::table('projects')->insertGetId([
            'project' => 'Other Project',
            'address' => 'Address 2',
            'is_active' => 1,
            'create_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $selectedProjectHeadId = DB::table('project_head_subheads')->insertGetId([
            'project_id' => $selectedProjectId,
            'head_accounting_id' => null,
            'subhead_accounting_id' => null,
            'plot_id' => null,
            'customer_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherProjectHeadId = DB::table('project_head_subheads')->insertGetId([
            'project_id' => $otherProjectId,
            'head_accounting_id' => null,
            'subhead_accounting_id' => null,
            'plot_id' => null,
            'customer_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ledgers')->insert([
            [
                'type' => 'CR',
                'type_id' => 1,
                'project_head_subheads_id' => $selectedProjectHeadId,
                'customer_ledger_id' => null,
                'reference' => 'CR-1',
                'amount_in' => 1000,
                'amount_out' => 0,
                'detail' => 'Cash receive',
                'delete_reason' => null,
                'create_by' => 1,
                'update_by' => null,
                'is_active' => 1,
                'status' => 'approved',
                'date' => '2026-06-03',
                'voucher_number' => '1001',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'PPR',
                'type_id' => 2,
                'project_head_subheads_id' => $selectedProjectHeadId,
                'customer_ledger_id' => 10,
                'reference' => 'PPR-1',
                'amount_in' => 2000,
                'amount_out' => 0,
                'detail' => 'Plot payment receive',
                'delete_reason' => null,
                'create_by' => 1,
                'update_by' => null,
                'is_active' => 1,
                'status' => 'pending',
                'date' => '2026-06-03',
                'voucher_number' => '1002',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'CP',
                'type_id' => 3,
                'project_head_subheads_id' => $selectedProjectHeadId,
                'customer_ledger_id' => null,
                'reference' => 'CP-1',
                'amount_in' => 0,
                'amount_out' => 3000,
                'detail' => 'Cash payment',
                'delete_reason' => null,
                'create_by' => 1,
                'update_by' => null,
                'is_active' => 1,
                'status' => 'approved',
                'date' => '2026-06-03',
                'voucher_number' => '1003',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'PPR',
                'type_id' => 4,
                'project_head_subheads_id' => $otherProjectHeadId,
                'customer_ledger_id' => 11,
                'reference' => 'PPR-2',
                'amount_in' => 4000,
                'amount_out' => 0,
                'detail' => 'Other project plot payment',
                'delete_reason' => null,
                'create_by' => 1,
                'update_by' => null,
                'is_active' => 1,
                'status' => 'pending',
                'date' => '2026-06-03',
                'voucher_number' => '1004',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $request = Request::create(
            '/finance/voucher/data/in',
            'GET',
            ['draw' => 1, 'start' => 0, 'length' => 10],
            ['selected_action' => (string) $selectedProjectId]
        );

        $this->app->instance('request', $request);

        $response = app(VoucherController::class)->cash_in_data($request);
        $payload = $response->getData(true);
        $types = array_column($payload['data'], 'type');
        $voucherNumbers = array_column($payload['data'], 'voucher_number');

        $this->assertSame(1, $payload['recordsTotal']);
        $this->assertSame(1, $payload['recordsFiltered']);
        $this->assertContains('CR', $types);
        $this->assertNotContains('PPR', $types);
        $this->assertNotContains('CP', $types);
        $this->assertContains('CR-1001', $voucherNumbers);
        $this->assertNotContains('PPR-1002', $voucherNumbers);
        $this->assertNotContains('PPR-1004', $voucherNumbers);
    }

    private function createSchema(): void
    {
        Schema::dropAllTables();

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project');
            $table->string('address');
            $table->integer('is_active');
            $table->integer('create_by');
            $table->timestamps();
        });

        Schema::create('head_accountings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('subhead_accountings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('project_head_subheads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('head_accounting_id')->nullable();
            $table->unsignedBigInteger('subhead_accounting_id')->nullable();
            $table->unsignedBigInteger('plot_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->timestamps();
        });

        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->integer('type_id')->nullable();
            $table->unsignedBigInteger('project_head_subheads_id');
            $table->unsignedBigInteger('customer_ledger_id')->nullable();
            $table->string('reference')->nullable();
            $table->decimal('amount_in', 10, 2);
            $table->decimal('amount_out', 10, 2);
            $table->text('detail')->nullable();
            $table->string('delete_reason')->nullable();
            $table->integer('create_by')->nullable();
            $table->integer('update_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->nullable();
            $table->date('date');
            $table->string('voucher_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pending_updates', function (Blueprint $table) {
            $table->id();
            $table->string('table_name');
            $table->unsignedBigInteger('record_id');
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
        });
    }
}
