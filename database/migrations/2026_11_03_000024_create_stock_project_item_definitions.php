<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('projects') || !Schema::hasTable('stock_items')) {
            throw new LogicException('Project stock definitions require projects and stock_items.');
        }
        if (!Schema::hasTable('stock_project_item_definitions')) {
            Schema::create('stock_project_item_definitions', function (Blueprint $table): void {
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('item_id');
                $table->string('name');
                $table->string('unit', 40);
                $table->unsignedBigInteger('default_rate_minor')->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->primary(['project_id', 'item_id'], 'stock_project_item_definition_pk');
                $table->foreign('project_id', 'stock_project_item_definition_project_fk')->references('id')->on('projects')->restrictOnDelete();
                $table->foreign('item_id', 'stock_project_item_definition_item_fk')->references('id')->on('stock_items')->restrictOnDelete();
                $table->index(['project_id', 'name'], 'stock_project_item_definition_name_idx');
            });
        }
        // Each project starts with its own copy of the current shared metadata.
        // No stock item ID, transaction, movement or balance is rewritten.
        DB::table('projects')->orderBy('id')->select('id')->chunk(100, function ($projects): void {
            foreach ($projects as $project) {
                DB::table('stock_items')->whereNull('deleted_at')->orderBy('id')->select(['id', 'name', 'unit', 'default_rate_minor', 'is_active'])
                    ->chunk(500, function ($items) use ($project): void {
                        $now = now();
                        DB::table('stock_project_item_definitions')->insertOrIgnore($items->map(fn ($item) => [
                            'project_id' => $project->id, 'item_id' => $item->id,
                            'name' => $item->name, 'unit' => $item->unit,
                            'default_rate_minor' => $item->default_rate_minor, 'is_active' => $item->is_active,
                            'created_at' => $now, 'updated_at' => $now,
                        ])->all());
                    });
            }
        });
    }

    public function down(): void
    {
        // Preserve project definitions and historical item associations.
    }
};
