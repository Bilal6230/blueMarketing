<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separate editable business source state from append-only posting evidence.
 * Backfill existing sources as active. Never touch allocation/posting lines.
 */
return new class extends Migration
{
    private const TABLES = ['finance_plot_receipts','finance_plot_purchases'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                throw new \RuntimeException('Plot voucher table missing: '.$table);
            }
        }
        foreach (self::TABLES as $table) {
            if (!Schema::hasColumn($table,'is_active')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->boolean('is_active')->default(true)->index();
                });
            }
            if (!Schema::hasColumn($table,'deleted_at')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        // Never erase lifecycle history/columns on rollback. Existing sources
        // may already have been logically archived by a later application.
    }
};
