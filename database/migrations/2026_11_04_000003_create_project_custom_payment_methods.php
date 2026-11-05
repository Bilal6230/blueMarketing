<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('projects')) throw new RuntimeException('Custom methods require projects.');
        if (!Schema::hasTable('project_custom_payment_methods')) {
            Schema::create('project_custom_payment_methods', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->string('code', 20);
                $table->string('name', 80);
                // Match legacy customer_ledger.payment_type (signed BIGINT)
                // so MySQL can enforce the composite method/project/behavior FK.
                $table->bigInteger('base_method_code');
                $table->boolean('is_active')->default(true);
                $table->foreignId('updated_by')->nullable();
                $table->timestamps();
                $table->unique(['project_id', 'code'], 'project_custom_payment_code_uq');
                $table->unique(['id', 'project_id', 'base_method_code'], 'project_custom_payment_scope_uq');
            });
        }
        if (!Schema::hasTable('project_custom_payment_method_audits')) {
            Schema::create('project_custom_payment_method_audits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('method_id')->constrained('project_custom_payment_methods')->restrictOnDelete();
                $table->string('action', 12);
                $table->text('old_values')->nullable();
                $table->text('new_values');
                $table->foreignId('actor_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
        $indexes = Schema::getIndexes('project_custom_payment_methods');
        if (!collect($indexes)->contains(fn (array $index): bool => ($index['name'] ?? null) === 'project_custom_payment_scope_uq')) {
            Schema::table('project_custom_payment_methods', fn (Blueprint $table) =>
                $table->unique(['id', 'project_id', 'base_method_code'], 'project_custom_payment_scope_uq'));
        }
    }

    public function down(): void
    {
        // Retain custom method definitions and audit history.
    }
};
