<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plot_definitions')) {
            Schema::create('plot_definitions', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->enum('kind', ['category', 'type', 'size', 'unit']);
                $table->string('code', 40);
                $table->string('name', 120);
                $table->decimal('size_value', 10, 2)->nullable();
                $table->string('unit_code', 20)->nullable();
                $table->unsignedBigInteger('unit_definition_id')->nullable();
                $table->unsignedTinyInteger('legacy_value')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->foreign('project_id', 'plot_definitions_project_fk')
                    ->references('id')->on('projects')->restrictOnDelete();
                $table->foreign(['unit_definition_id', 'project_id'], 'plot_definitions_unit_project_fk')
                    ->references(['id', 'project_id'])->on('plot_definitions')->restrictOnDelete();
                $table->unique(['project_id', 'kind', 'code'], 'plot_definitions_project_kind_code_unique');
                $table->unique(['id', 'project_id'], 'plot_definitions_id_project_unique');
                $table->index(['project_id', 'kind', 'is_active'], 'plot_definitions_project_kind_active_idx');
            });
        }

        if (!Schema::hasTable('plot_definition_rate_revisions')) {
            Schema::create('plot_definition_rate_revisions', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('plot_definition_id');
                $table->unsignedBigInteger('project_id');
                $table->decimal('rate_per_unit', 12, 2);
                $table->date('effective_from');
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->foreign('project_id', 'plot_definition_rate_project_fk')
                    ->references('id')->on('projects')->restrictOnDelete();
                $table->foreign(['plot_definition_id', 'project_id'], 'plot_definition_rate_definition_project_fk')
                    ->references(['id', 'project_id'])->on('plot_definitions')->restrictOnDelete();
                $table->unique(['plot_definition_id', 'effective_from'], 'plot_definition_rate_effective_unique');
            });
        }

        if (!Schema::hasTable('plot_definition_audits')) {
            Schema::create('plot_definition_audits', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('plot_definition_id');
                $table->string('action', 30);
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('reason', 500)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->foreign('project_id', 'plot_definition_audit_project_fk')
                    ->references('id')->on('projects')->restrictOnDelete();
                $table->foreign(['plot_definition_id', 'project_id'], 'plot_definition_audit_definition_project_fk')
                    ->references(['id', 'project_id'])->on('plot_definitions')->restrictOnDelete();
                $table->index(['project_id', 'plot_definition_id', 'created_at'], 'plot_definition_audit_lookup_idx');
            });
        }

        if (Schema::hasTable('plots') && Schema::hasTable('plot_definitions')) {
            Schema::table('plots', function (Blueprint $table): void {
                foreach (['category', 'type', 'size', 'unit'] as $kind) {
                    $column = 'plot_'.$kind.'_definition_id';
                    if (!Schema::hasColumn('plots', $column)) {
                        $table->unsignedBigInteger($column)->nullable()->index($column.'_idx');
                        $table->foreign([$column, 'project_id'], $column.'_project_fk')
                            ->references(['id', 'project_id'])->on('plot_definitions')->restrictOnDelete();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Retain definition links and effective rates; removing them could detach historical plot inventory.
    }
};
