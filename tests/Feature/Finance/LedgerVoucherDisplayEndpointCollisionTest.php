<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\LedgerController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Endpoint-level provenance regression: calls the real DataTables controller.
 * An unknown legacy source retains the original number; verified typed sources use the correct header.
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
            $table->unsignedBigInteger('project_id')->default(7);
            $table->softDeletes();
        });
        Schema::create('ledger_source_attributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ledger_id')->unique();
            $table->unsignedBigInteger('project_id');
            $table->string('source_kind');
            $table->unsignedBigInteger('source_id');
            $table->string('evidence_kind');
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

    public function test_genuine_jv_header_number_is_used_when_link_is_known(): void
    {
        DB::table('projects')->insert(['id' => 7, 'project' => 'Test']);
        DB::table('project_head_subheads')->insert(['id' => 3, 'project_id' => 7]);
        DB::table('journal_vouchers')->insert(['id' => 42, 'voucher_number' => '9002']);
        // Independent typed attribution is the proof; numeric type_id alone is not.
        DB::table('ledger_source_attributions')->insert([
            'ledger_id' => 2, 'project_id' => 7, 'source_kind' => 'journal_voucher',
            'source_id' => 42, 'evidence_kind' => 'new_write_transaction',
        ]);
        DB::table('ledgers')->insert([
            'id' => 2, 'type' => 'JV', 'type_id' => 42,
            'project_head_subheads_id' => 3, 'voucher_number' => '7002',
            'is_active' => true, 'date' => '2026-10-26',
        ]);

        $request = Request::create('/fetch-data-url', 'POST', ['draw' => 1, 'start' => 0, 'length' => 10]);
        $request->cookies->set('selected_action', '7');
        $this->app->instance('request', $request);

        $response = app(LedgerController::class)->fetch_data_url($request);
        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $payload['recordsFiltered'], json_encode($payload));
        $this->assertCount(1, $payload['data']);
        // Verified ledger→JV attribution preserves the genuine header number.
        $this->assertSame('JV-9002', $payload['data'][0]['display_voucher_number']);
    }

    public function test_verified_commission_ledger_displays_cv_header_not_colliding_jv_header(): void
    {
        Schema::create('commision_vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('voucher_number');
        });
        DB::table('projects')->insert(['id' => 7, 'project' => 'Test']);
        DB::table('project_head_subheads')->insert(['id' => 3, 'project_id' => 7]);
        DB::table('journal_vouchers')->insert(['id' => 41, 'voucher_number' => '9001']);
        DB::table('commision_vouchers')->insert(['id' => 41, 'project_id' => 7, 'voucher_number' => '22']);
        DB::table('ledgers')->insert([
            'id' => 1, 'type' => 'JV', 'type_id' => 41,
            'project_head_subheads_id' => 3, 'voucher_number' => '7001',
            'is_active' => true, 'date' => '2026-10-26',
        ]);
        DB::table('ledger_source_attributions')->insert([
            'ledger_id' => 1, 'project_id' => 7, 'source_kind' => 'commission_voucher',
            'source_id' => 41, 'evidence_kind' => 'manual_review',
        ]);
        $request = Request::create('/fetch-data-url', 'POST', ['draw' => 1, 'start' => 0, 'length' => 10]);
        $request->cookies->set('selected_action', '7');
        $this->app->instance('request', $request);
        $payload = json_decode(app(LedgerController::class)->fetch_data_url($request)->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('CV-22', $payload['data'][0]['display_voucher_number']);
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
        $this->assertSame('7', (string) getSelectedTown(), 'Selected project cookie not visible to request helper.');
        $this->assertSame(1, DB::table('ledgers')->where('project_head_subheads_id', 3)->count());

        // Call the real controller/DataTables callback without authentication
        // or a live database; this isolates the display behavior only.
        $response = app(LedgerController::class)->fetch_data_url($request);
        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $payload['recordsFiltered'], 'DataTables returned: ' . json_encode($payload));
        $this->assertCount(1, $payload['data']);

        // No independent source evidence exists, so the original ledger number survives.
        $this->assertSame('JV-7001', $payload['data'][0]['display_voucher_number']);
    }
}
