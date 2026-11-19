<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if(!Schema::hasTable('labour_ledgers'))
            throw new \RuntimeException('Existing labour ledger table is required.');
        if(!Schema::hasColumn('labour_ledgers','is_active')) {
            Schema::table('labour_ledgers',function(Blueprint $table){
                $table->boolean('is_active')->default(true);
            });
        }
        if(!Schema::hasColumn('labour_ledgers','deleted_at')) {
            Schema::table('labour_ledgers',function(Blueprint $table){
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        // Preserve archived payroll evidence.
    }
};
