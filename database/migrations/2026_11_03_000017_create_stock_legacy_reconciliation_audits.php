<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['stock_entries', 'stock_bills', 'stock_movements', 'stock_entry_lines', 'stock_bill_lines'] as $table) {
            if (!Schema::hasColumn($table, 'project_id')) {
                throw new LogicException('Install the project stock history migration before reconciliation audit.');
            }
        }
        if (Schema::hasTable('stock_legacy_reconciliation_audits')) return;

        Schema::create('stock_legacy_reconciliation_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('record_type', 24);
            $table->unsignedBigInteger('record_id');
            $table->unsignedBigInteger('project_id');
            $table->string('reason', 500);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['record_type', 'record_id'], 'stock_legacy_reconcile_record_uq');
            $table->index(['project_id', 'created_at'], 'stock_legacy_reconcile_project_idx');
            $table->foreign('project_id', 'stock_legacy_reconcile_project_fk')->references('id')->on('projects')->restrictOnDelete();
            $table->foreign('actor_id', 'stock_legacy_reconcile_actor_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Keep attribution audit history when rolling back application code.
    }
};
