<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nullable source relationship for legacy labour-payment history.
 * Existing rows cannot be joined by week/amount or numeric type_id safely.
 */
return new class extends Migration
{
    public function up(): void
    {
        if(!Schema::hasTable('labour_ledgers')||!Schema::hasTable('customer_ledger')) {
            throw new \RuntimeException('Labour and customer ledger source tables are required.');
        }
        if(!Schema::hasColumn('labour_ledgers','customer_ledger_id')) {
            Schema::table('labour_ledgers',function(Blueprint $table): void {
                $table->unsignedBigInteger('customer_ledger_id')->nullable()->index();
                $table->foreign('customer_ledger_id','labour_ledgers_customer_source_fk')
                    ->references('id')->on('customer_ledger')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Never automatically drop historical FK evidence.
    }
};
