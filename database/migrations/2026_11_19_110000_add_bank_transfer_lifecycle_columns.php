<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lifecycle only; no new finance table or destructive backfill. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('finance_bank_transfer_sources')) {
            throw new \RuntimeException('Existing bank transfer source table is required.');
        }
        if (!Schema::hasColumn('finance_bank_transfer_sources','is_active')) {
            Schema::table('finance_bank_transfer_sources',function(Blueprint $table){
                $table->boolean('is_active')->default(true);
            });
        }
        if (!Schema::hasColumn('finance_bank_transfer_sources','deleted_at')) {
            Schema::table('finance_bank_transfer_sources',function(Blueprint $table){
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        // Historical accounting lifecycle fields may contain real evidence.
        // Never drop the columns automatically during a rollback.
    }
};
