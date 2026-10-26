<?php

namespace Tests\Feature\Finance;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * RED diagnostic for the currently ambiguous JV report-number lookup.
 * This recreates the exact ledger/JV-header lookup in LedgerController's
 * display_voucher_number callback; it does NOT invoke the DataTables endpoint.
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
        $voucherNumber = $ledger->voucher_number;

        // Mirror the active reader in LedgerController::fetch_data_url.
        if ($ledger->type === 'JV' && !empty($ledger->type_id)) {
            $header = DB::table('journal_vouchers')
                ->where('id', $ledger->type_id)
                ->first(['id', 'voucher_number']);
            if ($header && !empty($header->voucher_number)) {
                $voucherNumber = $header->voucher_number;
            }
        }

        // An ambiguous legacy row should not be confidently relabeled
        // as another voucher. RED until source provenance is available.
        $this->assertSame('JV-7001', $ledger->type . '-' . $voucherNumber);
    }
}
