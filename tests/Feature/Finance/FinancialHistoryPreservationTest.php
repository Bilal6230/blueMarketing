<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\CommissionVoucherController;
use App\Http\Controllers\JournalVoucherController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * RED safety characterisation tests: do not treat current destructive deletion as acceptable.
 * Run explicitly with: php artisan test --filter=FinancialHistoryPreservationTest
 * Uses in-memory SQLite enforced by phpunit.xml; NEVER point tests at business MySQL.
 */
#[Group('finance-safety-red')]
class FinancialHistoryPreservationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDefaultConnection() !== 'sqlite' ||
            DB::connection()->getDatabaseName() !== ':memory:') {
            $this->fail('Refusing destructive regression setup outside isolated in-memory SQLite.');
        }

        Schema::dropAllTables();

        Schema::create('commision_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number');
            $table->date('date');
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('commision_voucher_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('commision_voucher_id');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('journal_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number');
            $table->date('date');
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('journal_voucher_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journal_voucher_id');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->integer('type_id');
            $table->decimal('amount_in', 15, 2)->default(0);
            $table->decimal('amount_out', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function test_deleting_commission_voucher_preserves_original_ledger_rows(): void
    {
        $voucherId = DB::table('commision_vouchers')->insertGetId([
            'voucher_number' => 'CV-TEST-1',
            'date' => '2026-10-26',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $ledgerId = DB::table('ledgers')->insertGetId([
            'type' => 'CV', 'type_id' => $voucherId,
            'amount_in' => 100, 'amount_out' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        app(CommissionVoucherController::class)->destroy($voucherId);

        $this->assertDatabaseHas('ledgers', ['id' => $ledgerId],
            'A voucher delete must preserve source ledger history for reversal/voiding.');
    }

    public function test_deleting_journal_voucher_preserves_original_ledger_rows(): void
    {
        $voucherId = DB::table('journal_vouchers')->insertGetId([
            'voucher_number' => 'JV-TEST-1',
            'date' => '2026-10-26',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $ledgerId = DB::table('ledgers')->insertGetId([
            'type' => 'JV', 'type_id' => $voucherId,
            'amount_in' => 100, 'amount_out' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        app(JournalVoucherController::class)->destroy($voucherId);

        $this->assertDatabaseHas('ledgers', ['id' => $ledgerId],
            'A journal voucher delete must preserve source ledger history for reversal/voiding.');
    }
}
