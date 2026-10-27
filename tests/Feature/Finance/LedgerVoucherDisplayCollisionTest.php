<?php

namespace Tests\Feature\Finance;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Unit-level historical collision regression for the active fail-closed display service.
 * No source attribution exists, so the original ledger number must survive.
 */
class LedgerVoucherDisplayCollisionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDefaultConnection() !== 'sqlite' ||
            DB::connection()->getDatabaseName() !== ':memory:') {
            $this->fail('Refusing fixture outside SQLite :memory:.');
        }

        Schema::dropAllTables();
        Schema::create('journal_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number');
        });
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->unsignedBigInteger('type_id');
            $table->string('voucher_number');
        });
    }

    public function test_legacy_commission_jv_row_must_not_display_unrelated_jv_header_number(): void
    {
        // Historical CV could have written JV/type_id=41; a separate JV
        // with the same numeric ID makes source identity ambiguous.
        DB::table('journal_vouchers')->insert([
            'id' => 41, 'voucher_number' => '9001',
        ]);
        DB::table('ledgers')->insert([
            'id' => 1, 'type' => 'JV', 'type_id' => 41,
            'voucher_number' => '7001',
        ]);

        $ledger = DB::table('ledgers')->find(1);
        $display = app(\App\Services\Finance\LedgerVoucherDisplayService::class)
            ->display($ledger, 7);

        $this->assertSame('JV-7001', $display);
    }
}
