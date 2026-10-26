<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\LedgerController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * RED endpoint-level diagnostic: calls the real DataTables controller.
 * The legacy row is SOURCE-AMBIGUOUS by design, not proven to be CV.
 */
class LedgerVoucherDisplayEndpointCollisionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDefaultConnection() !== 'sqlite' ||
            DB::connection()->getDatabaseName() !== ':memory:') {
            $this->fail('Fixture requires isolated SQLite :memory:.');
        }

        Schema::dropAllTables();
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project')->nullable();
        });
        Schema::create('head_accountings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
        });
        Schema::create('subhead_accountings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
        });
        Schema::create('project_head_subheads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('head_accounting_id')->nullable();
            $table->unsignedBigInteger('subhead_accounting_id')->nullable();
        });
        Schema::create('journal_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number');
        });
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->unsignedBigInteger('type_id');
            $table->unsignedBigInteger('project_head_subheads_id');
            $table->string('voucher_number');
            $table->boolean('is_active')->default(true);
            $table->date('date')->nullable();
            $table->unsignedBigInteger('create_by')->nullable();
        });
    }

    public function test_actual_datatables_reader_does_not_override_ambiguous_legacy_number(): void
    {
        DB::table('projects')->insert(['id' => 7, 'project' => 'Isolated Test']);
        DB::table('project_head_subheads')->insert(['id' => 3, 'project_id' => 7]);
        DB::table('journal_vouchers')->insert(['id' => 41, 'voucher_number' => '9001']);
        DB::table('ledgers')->insert([
            'id' => 1, 'type' => 'JV', 'type_id' => 41,
            'project_head_subheads_id' => 3, 'voucher_number' => '7001',
            'is_active' => true, 'date' => '2026-10-26',
        ]);

        $request = Request::create('/fetch-data-url', 'POST', ['draw' => 1, 'start' => 0, 'length' => 10]);
        $request->cookies->set('selected_action', '7');
        $this->app->instance('request', $request);

        // Call the real controller/DataTables callback without authentication
        // or a live database; this isolates the display behavior only.
        $response = app(LedgerController::class)->fetch_data_url($request);
        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $payload['recordsFiltered']);
        $this->assertCount(1, $payload['data']);

        // This should be sourced from a known, typed voucher posting in the
        // future. Today the reader incorrectly trusts a colliding JV header.
        $this->assertSame('JV-7001', $payload['data'][0]['display_voucher_number']);
    }
}
