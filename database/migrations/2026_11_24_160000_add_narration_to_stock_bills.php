<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('stock_bills', 'narration')) {
            Schema::table('stock_bills', function (Blueprint $table): void {
                $table->text('narration')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_bills', 'narration')) {
            Schema::table('stock_bills', function (Blueprint $table): void {
                $table->dropColumn('narration');
            });
        }
    }
};
