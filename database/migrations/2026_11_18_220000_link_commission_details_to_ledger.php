<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a real, nullable CV detail-to-ledger link to the existing ledger table.
 * Historical CV legs remain NULL, preserved and validated by their exact
 * voucher/account/amount multiset. No existing rows are rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ledgers') || !Schema::hasTable('commision_voucher_details')) {
            throw new \RuntimeException('Commission detail relationship prerequisites are missing.');
        }
        if (!Schema::hasColumn('ledgers','commision_voucher_detail_id')) {
            Schema::table('ledgers', function(Blueprint $table): void {
                $table->unsignedBigInteger('commision_voucher_detail_id')->nullable()
                    ->index('idx_ledger_commision_detail');
            });
        }
        // FK creation is additive and RESTRICT-only. Do not attempt to
        // alter or delete historical ledger/accounting evidence.
        $driver=DB::connection()->getDriverName();
        if ($driver !== 'mysql') return;
        $db=DB::connection()->getDatabaseName();
        $existing=DB::selectOne('SELECT CONSTRAINT_NAME AS name
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?
              AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1',
            [$db,'ledgers','commision_voucher_detail_id']);
        if ($existing) {
            if ($existing->name !== 'fk_ledger_commision_detail') {
                throw new \RuntimeException('Unexpected existing Commission detail foreign key. Inspect before continuing.');
            }
            return;
        }
        $orphan=(int)DB::table('ledgers as l')
            ->leftJoin('commision_voucher_details as d','d.id','=','l.commision_voucher_detail_id')
            ->whereNotNull('l.commision_voucher_detail_id')->whereNull('d.id')->count();
        if ($orphan) throw new \RuntimeException('Commission detail FK blocked by orphan linked ledgers.');
        Schema::table('ledgers',function(Blueprint $table): void {
            $table->foreign('commision_voucher_detail_id','fk_ledger_commision_detail')
                ->references('id')->on('commision_voucher_details')
                ->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive: never drop an accounting relationship
        // column holding recorded ledger identities on rollback.
    }
};
