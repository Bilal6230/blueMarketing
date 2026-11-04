<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('stock_items')) throw new LogicException('Stock item import requires stock_items.');
        if (!Schema::hasTable('stock_item_import_batches')) {
            Schema::create('stock_item_import_batches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->string('status', 20)->default('staged');
                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('added_rows')->default(0);
                $table->unsignedInteger('updated_rows')->default(0);
                $table->unsignedInteger('skipped_rows')->default(0);
                $table->unsignedInteger('failed_rows')->default(0);
                $table->timestamp('applied_at')->nullable();
                $table->timestamps();
                $table->index(['project_id', 'status', 'created_at'], 'stock_item_import_batch_lookup_idx');
            });
        }
        if (!Schema::hasTable('stock_item_import_rows')) {
            Schema::create('stock_item_import_rows', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('batch_id')->constrained('stock_item_import_batches')->restrictOnDelete();
                $table->unsignedInteger('row_number');
                $table->foreignId('stock_item_id')->nullable()->constrained('stock_items')->restrictOnDelete();
                $table->string('action', 12);
                $table->json('payload');
                $table->json('errors')->nullable();
                $table->timestamps();
                $table->unique(['batch_id', 'row_number'], 'stock_item_import_row_unique');
                $table->index(['batch_id', 'action'], 'stock_item_import_row_action_idx');
            });
        }
    }

    public function down(): void
    {
        // Retain the import audit trail across rollback.
    }
};
