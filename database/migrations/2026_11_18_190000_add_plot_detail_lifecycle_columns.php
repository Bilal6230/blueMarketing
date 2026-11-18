<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Non-destructive support for in-place Plot voucher line revisions. Archived
 * lines remain physically present with source snapshots preserving values.
 * Shared posting lines used by all vouchers default to active.
 */
return new class extends Migration {
    public function up(): void
    {
        foreach ([
            'finance_plot_receipt_allocations',
            'finance_plot_purchase_items',
            'finance_posting_lines',
        ] as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table,'is_active')) continue;
            Schema::table($table,function(Blueprint $blueprint): void {
                $blueprint->boolean('is_active')->default(true)->index();
            });
        }
    }

    public function down(): void
    {
        // Never remove lifecycle flags required to interpret historical rows.
    }
};
