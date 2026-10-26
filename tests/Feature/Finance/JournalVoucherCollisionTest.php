<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\JournalVoucherController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * RED integration regression: a JV detail ID collides with another JV header ID.
 * Runs ONLY against an in-memory SQLite database, never MySQL.
 */
#[Group('finance-safety-red')]
class JournalVoucherCollisionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDefaultConnection() !== 'sqlite' ||
            DB::connection()->getDatabaseName() !== ':memory:') {
            $this->fail('Refusing test setup outside isolated SQLite :memory:.');
        }

        Schema::dropAllTables();

        Schema::create('journal_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number');
            $table->string('reference')->nullable();
            $table->date('date');
            $table->text('description')->nullable();
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('journal_voucher_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journal_voucher_id');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('project_head_subheads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
        });
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->unsignedBigInteger('type_id');
            $table->string('voucher')->nullable();
            $table->unsignedBigInteger('voucher_number')->nullable();
            $table->unsignedBigInteger('project_head_subheads_id')->nullable();
            $table->string('reference')->nullable();
            $table->decimal('amount_in', 15, 2)->default(0);
            $table->decimal('amount_out', 15, 2)->default(0);
            $table->text('detail')->nullable();
            $table->unsignedBigInteger('create_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->nullable();
            $table->date('date')->nullable();
            $table->timestamps();
        });
    }

    public function test_updating_one_jv_preserves_unrelated_jv_ledger_when_detail_id_collides(): void
    {
        // Header 88 belongs to ANOTHER JV. Detail 88 belongs to header 41.
        // The current updater deletes JV ledger type_id in [41, 88].
        DB::table('journal_vouchers')->insert([
            ['id' => 41, 'voucher_number' => 'JV-41', 'date' => '2026-10-26',
             'created_at' => now(), 'updated_at' => now()],
            ['id' => 88, 'voucher_number' => 'JV-88', 'date' => '2026-10-26',
             'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('journal_voucher_details')->insert([
            'id' => 88, 'journal_voucher_id' => 41,
            'account_id' => 1, 'debit' => 10, 'credit' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $unrelatedLedgerId = DB::table('ledgers')->insertGetId([
            'type' => 'JV', 'type_id' => 88,
            'amount_in' => 400, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $request = Request::create('/journal-voucher/41', 'PUT', [
            'voucher_number' => 'JV-41', 'date' => '2026-10-26',
            'description' => 'Header 41 update',
            'accounts' => [1], 'sub_accounts' => [1],
            'debit' => [0], 'credit' => [0],
        ]);
        $this->app->instance('request', $request);

        $response = app(JournalVoucherController::class)->update($request, 41);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('journal', $response->getTargetUrl() ?: '');
        $this->assertDatabaseHas('ledgers', [
            'id' => $unrelatedLedgerId, 'type' => 'JV', 'type_id' => 88,
        ]);
    }
}
