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
            $table->unsignedBigInteger('project_id');
            $table->string('type')->nullable();
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
        Schema::create('head_accountings', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->boolean('is_active')->default(true);
        });
        Schema::create('subhead_accountings', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->boolean('is_active')->default(true);
        });
        Schema::create('project_head_subheads', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('head_accounting_id');
            $table->unsignedBigInteger('subhead_accounting_id');
            $table->softDeletes();
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
        // Build a VALID balanced revision: rejecting it must not erase the
        // unrelated voucher 88 ledger whose type_id collides with detail 88.
        DB::table('head_accountings')->insert([
            ['id'=>1,'name'=>'Head 1','is_active'=>1],
            ['id'=>2,'name'=>'Head 2','is_active'=>1],
        ]);
        DB::table('subhead_accountings')->insert([
            ['id'=>1,'name'=>'Child 1','is_active'=>1],
            ['id'=>2,'name'=>'Child 2','is_active'=>1],
        ]);
        DB::table('project_head_subheads')->insert([
            ['id'=>1,'project_id'=>7,'head_accounting_id'=>1,'subhead_accounting_id'=>1],
            ['id'=>2,'project_id'=>7,'head_accounting_id'=>2,'subhead_accounting_id'=>2],
        ]);
        DB::table('journal_vouchers')->insert([
            ['id' => 41, 'project_id' => 7, 'voucher_number' => 'JV-41', 'date' => '2026-10-26',
             'created_at' => now(), 'updated_at' => now()],
            ['id' => 88, 'project_id' => 7, 'voucher_number' => 'JV-88', 'date' => '2026-10-26',
             'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('journal_voucher_details')->insert([
            ['id'=>88,'journal_voucher_id'=>41,'account_id'=>1,
             'debit'=>10,'credit'=>0,'created_at'=>now(),'updated_at'=>now()],
            ['id'=>89,'journal_voucher_id'=>41,'account_id'=>2,
             'debit'=>0,'credit'=>10,'created_at'=>now(),'updated_at'=>now()],
        ]);
        $unrelatedLedgerId = DB::table('ledgers')->insertGetId([
            'type' => 'JV', 'type_id' => 88,
            'amount_in' => 400, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $request = Request::create('/journal-voucher/41', 'PUT', [
            'voucher_number' => 'JV-41', 'date' => '2026-10-26',
            'description' => 'Header 41 update',
            'accounts' => [1,2], 'sub_accounts' => [1,2],
            'debit' => ['10.00','0'], 'credit' => ['0','10.00'],
        ]);
        $request->cookies->set('selected_action', '7');
        $this->app->instance('request', $request);

        try {
            app(JournalVoucherController::class)->update($request, 41);
            $this->fail('Unsafe JV update must be blocked.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertDatabaseHas('ledgers', [
            'id' => $unrelatedLedgerId, 'type' => 'JV', 'type_id' => 88,
        ]);

    }
}
