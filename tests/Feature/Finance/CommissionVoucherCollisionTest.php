<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\CommissionVoucherController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * RED integration regression: independent JV and CV IDs collide.
 * Runs ONLY against an in-memory SQLite database, never MySQL.
 */
#[Group('finance-safety-red')]
class CommissionVoucherCollisionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDefaultConnection() !== 'sqlite' ||
            DB::connection()->getDatabaseName() !== ':memory:') {
            $this->fail('Refusing test setup outside isolated SQLite :memory:.');
        }

        Schema::dropAllTables();

        Schema::create('commision_vouchers', function (Blueprint $table) {
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
        Schema::create('commision_voucher_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('commision_voucher_id');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('journal_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number');
        });
        Schema::create('project_head_subheads', function (Blueprint $table) {\n            $table->id();\n            $table->unsignedBigInteger('project_id')->nullable();\n        });\n        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->unsignedBigInteger('type_id');
            $table->string('voucher')->nullable();\n            $table->unsignedBigInteger('voucher_number')->nullable();
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

    public function test_updating_commission_voucher_does_not_delete_journal_voucher_with_same_id(): void
    {
        $id = 41;
        DB::table('commision_vouchers')->insert([
            'id' => $id, 'voucher_number' => 'CV-41', 'date' => '2026-10-26',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('journal_vouchers')->insert([
            'id' => $id, 'voucher_number' => 'JV-41',
        ]);
        $jvLedgerId = DB::table('ledgers')->insertGetId([
            'type' => 'JV', 'type_id' => $id, 'amount_in' => 400,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Isolated fixture ensures update passes validation but creates no
        // replacement lines; this directly exercises the dangerous delete.
        $request = Request::create('/commission-voucher/' . $id, 'PUT', [
            'voucher_number' => 'CV-41', 'date' => '2026-10-26',
            'description' => 'Safe update expected',
            'accounts' => [1], 'sub_accounts' => [1],
            'debit' => [0], 'credit' => [0],
        ]);
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $this->app->instance('request', $request);

        $response = app(CommissionVoucherController::class)->update($request, $id);\n        $this->assertSame(302, $response->getStatusCode());\n        $this->assertStringContainsString('commision', $response->getTargetUrl());

        $this->assertDatabaseHas('ledgers', [
            'id' => $jvLedgerId, 'type' => 'JV', 'type_id' => $id,
        ]);
    }
}
