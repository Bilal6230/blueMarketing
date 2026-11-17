<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stock_parties')) {
            return;
        }

        if (!Schema::hasColumn('stock_parties', 'vendor_code')) {
            Schema::table('stock_parties', function (Blueprint $table) {
                $table->string('vendor_code', 40)->nullable();
                $table->unique('vendor_code', 'stock_parties_vendor_code_unique');
            });
        }
    }

    public function down(): void
    {
        // Retain issued vendor codes if application code is rolled back.
    }
};
