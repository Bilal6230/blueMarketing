<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Isolated disposable SQLite proof; never connects to finance clones. */
final class JournalDetailProvenanceMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');
        Schema::create('journal_voucher_details', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->unsignedBigInteger('type_id');
        });
    }

    public function test_new_jv_detail_fk_and_uniqueness_preserve_legacy_nulls(): void
    {
        $migration = require base_path('database/migrations/2026_10_29_000001_add_journal_voucher_detail_provenance_to_ledgers.php');
        $migration->up();
        $this->assertTrue(Schema::hasColumn('ledgers', 'journal_voucher_detail_id'));

        DB::table('journal_voucher_details')->insert(['id' => 10]);
        DB::table('ledgers')->insert([
            ['id' => 1, 'type' => 'JV', 'type_id' => 9, 'journal_voucher_detail_id' => null],
            ['id' => 2, 'type' => 'JV', 'type_id' => 9, 'journal_voucher_detail_id' => null],
            ['id' => 3, 'type' => 'JV', 'type_id' => 10, 'journal_voucher_detail_id' => 10],
        ]);
        $this->assertSame(3, DB::table('ledgers')->count());

        try {
            DB::table('ledgers')->insert([
                'id' => 4, 'type' => 'JV', 'type_id' => 10, 'journal_voucher_detail_id' => 10,
            ]);
            $this->fail('Duplicate journal detail posting should fail.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame(3, DB::table('ledgers')->count());
        }
        try {
            DB::table('ledgers')->insert([
                'id' => 5, 'type' => 'JV', 'type_id' => 999, 'journal_voucher_detail_id' => 999,
            ]);
            $this->fail('Missing journal detail FK should fail.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame(3, DB::table('ledgers')->count());
        }
    }
}
