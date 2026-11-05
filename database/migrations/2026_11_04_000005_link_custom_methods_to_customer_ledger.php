<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customer_ledger') || !Schema::hasTable('project_custom_payment_methods')) {
            throw new RuntimeException('Custom payment method linkage requires customer_ledger and custom method definitions.');
        }
        if (!Schema::hasColumn('customer_ledger', 'custom_payment_method_id')) {
            Schema::table('customer_ledger', function (Blueprint $table): void {
                $table->unsignedBigInteger('custom_payment_method_id')->nullable();
            });
        }
        if (DB::getDriverName() === 'mysql') {
            // Legacy payment_type is signed BIGINT; make the referenced behavior
            // key byte-compatible before MySQL creates its composite FK.
            DB::statement('ALTER TABLE project_custom_payment_methods MODIFY base_method_code BIGINT NOT NULL');
        }
        $methodIndexes = Schema::getIndexes('project_custom_payment_methods');
        if (!collect($methodIndexes)->contains(fn (array $index): bool => ($index['name'] ?? null) === 'project_custom_payment_scope_uq')) {
            Schema::table('project_custom_payment_methods', fn (Blueprint $table) =>
                $table->unique(['id', 'project_id', 'base_method_code'], 'project_custom_payment_scope_uq'));
        }

        $indexes = Schema::getIndexes('customer_ledger');
        if (!collect($indexes)->contains(fn (array $index): bool => ($index['name'] ?? null) === 'customer_ledger_custom_method_idx')) {
            Schema::table('customer_ledger', fn (Blueprint $table) =>
                $table->index(['project_id', 'custom_payment_method_id', 'payment_type'], 'customer_ledger_custom_method_idx'));
        }
        $foreignKeys = Schema::getForeignKeys('customer_ledger');
        if (!collect($foreignKeys)->contains(fn (array $foreignKey): bool => ($foreignKey['name'] ?? null) === 'customer_ledger_custom_method_scope_fk')) {
            Schema::table('customer_ledger', fn (Blueprint $table) =>
                $table->foreign(['custom_payment_method_id', 'project_id', 'payment_type'], 'customer_ledger_custom_method_scope_fk')
                    ->references(['id', 'project_id', 'base_method_code'])->on('project_custom_payment_methods')->restrictOnDelete());
        }
    }

    public function down(): void
    {
        // Preserve the selected method on historical source vouchers.
    }
};
