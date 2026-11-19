<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add non-destructive stock document lifecycle columns. Historical stock
 * movements and source details are archived, never hard-deleted.
 */
return new class extends Migration {
    public function up(): void
    {
        foreach(['stock_entry_lines','stock_bill_lines','stock_movements',
            'stock_bills'] as $table) {
            if(!Schema::hasTable($table))continue;
            if(!Schema::hasColumn($table,'deleted_at')) {
                Schema::table($table,function(Blueprint $t):void{$t->softDeletes();});
            }
        }
        if(Schema::hasTable('stock_movements')
            &&!Schema::hasColumn('stock_movements','is_active')) {
            Schema::table('stock_movements',function(Blueprint $t):void{
                $t->boolean('is_active')->default(true);
            });
        }
    }

    public function down(): void
    {
        // Historical finance and stock evidence must never be erased by a
        // routine rollback. Use a separately reviewed forward migration.
    }
};
