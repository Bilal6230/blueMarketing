<?php

namespace Tests\Feature\Finance;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 1X baseline: existing voucher-number helper semantics.
 * Does not authorize keeping the cross-table numbering rule.
 */
class CommissionVoucherNumberingBaselineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDefaultConnection() !== 'sqlite'
            || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->fail('Refusing schema setup outside SQLite :memory:.');
        }

        Schema::dropAllTables();
        Schema::create('journal_vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('voucher_number');
            $table->string('type')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function test_commission_listing_number_formatter_pads_without_header_lookup(): void
    {
        $this->assertSame('0000041', get_jv_number(41));
        $this->assertSame('0009001', get_jv_number(9001));
    }

    public function test_legacy_commission_number_seed_uses_jv_header_id_not_jv_voucher_serial(): void
    {
        DB::table('journal_vouchers')->insert([
            ['id' => 41, 'project_id' => 7, 'voucher_number' => '9001',
             'type' => 'JV', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 70, 'project_id' => 8, 'voucher_number' => '9009',
             'type' => 'JV', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $request = Request::create('/', 'GET');
        $request->cookies->set('selected_action', '7');
        $this->app->instance('request', $request);

        $this->assertSame('7', (string) getSelectedTown());
        $this->assertEquals(41, getLastJvId());
        $this->assertEquals(42, getLastJvId() + 1);
        $this->assertEquals('9001', getLastJvVNumber());
    }
}
