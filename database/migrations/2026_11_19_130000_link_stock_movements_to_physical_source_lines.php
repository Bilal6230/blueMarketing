<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Enforce exact physical stock line ownership for all NEW movements. */
return new class extends Migration
{
    public function up(): void
    {
        foreach(['stock_movements','stock_entry_lines','stock_bill_lines'] as $table){
            if(!Schema::hasTable($table)){
                throw new \RuntimeException('Stock movement source relationship baseline is missing: '.$table);
            }
        }
        if(!Schema::hasColumn('stock_movements','stock_entry_line_id')){
            Schema::table('stock_movements',function(Blueprint $table):void{
                $table->unsignedBigInteger('stock_entry_line_id')->nullable()->index();
                $table->foreign('stock_entry_line_id','stock_movement_entry_line_fk')
                    ->references('id')->on('stock_entry_lines')->restrictOnDelete();
            });
        }
        if(!Schema::hasColumn('stock_movements','stock_bill_line_id')){
            Schema::table('stock_movements',function(Blueprint $table):void{
                $table->unsignedBigInteger('stock_bill_line_id')->nullable()->index();
                $table->foreign('stock_bill_line_id','stock_movement_bill_line_fk')
                    ->references('id')->on('stock_bill_lines')->restrictOnDelete();
            });
        }
        // No historical auto-backfill: source_type + source_line_id must also
        // agree with parent ID/project/item and amount before being assigned.
    }

    public function down(): void
    {
        // Do not automatically discard accounting inventory source evidence.
    }
};
