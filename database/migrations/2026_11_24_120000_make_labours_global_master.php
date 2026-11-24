<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('labours') || !Schema::hasColumn('labours', 'project_id')) {
            return;
        }

        // labour_ledgers and potentially other dependent tables use a
        // composite FK (project_id, labour_id) -> labours(project_id, id).
        // A worker is now global, but the child activity's project_id MUST
        // remain: replace those FKs with labour_id -> labours.id rather than
        // disabling FK checks or deleting child project history.
        if (DB::getDriverName() !== 'mysql') {
            throw new \RuntimeException('Global labour migration requires MySQL FK metadata.');
        }
        $foreignColumns = collect(DB::select(<<<'SQL'
            SELECT TABLE_NAME AS child_table, CONSTRAINT_NAME AS constraint_name,
                   COLUMN_NAME AS child_column, REFERENCED_COLUMN_NAME AS parent_column
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME = 'labours'
              AND REFERENCED_COLUMN_NAME IS NOT NULL
            ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION
            SQL));
        $constraints = $foreignColumns->groupBy(fn ($column) =>
            $column->child_table . '|' . $column->constraint_name);

        // Validate all dependencies BEFORE making MySQL's nontransactional
        // DDL changes. Never delete an FK unless an exact ID replacement
        // can preserve referential integrity.
        $replacements = [];
        foreach ($constraints as $columns) {
            if (!$columns->contains(fn ($column) => $column->parent_column === 'project_id')) {
                continue;
            }
            $childTable = (string) $columns->first()->child_table;
            $foreignName = (string) $columns->first()->constraint_name;
            if ($columns->count() !== 2
                || !$columns->contains(fn ($column) =>
                    $column->parent_column === 'id' && $column->child_column === 'labour_id')
                || !$columns->contains(fn ($column) =>
                    $column->parent_column === 'project_id' && $column->child_column === 'project_id')
                || !Schema::hasColumn($childTable, 'labour_id')) {
                throw new \RuntimeException(
                    "Cannot safely replace {$childTable}.{$foreignName}; an unexpected labour FK depends on project_id."
                );
            }
            $replacements[] = ['table' => $childTable, 'name' => $foreignName];
        }

        foreach ($replacements as $replacement) {
            $tableName = $replacement['table'];
            Schema::table($tableName, function (Blueprint $table) use ($replacement): void {
                $table->dropForeign($replacement['name']);
            });
            $fkName = substr('labour_global_' . substr(hash('sha256', $tableName), 0, 16), 0, 64);
            Schema::table($tableName, function (Blueprint $table) use ($fkName): void {
                $table->foreign('labour_id', $fkName)->references('id')->on('labours');
            });
        }

        foreach (Schema::getForeignKeys('labours') as $foreign) {
            if (in_array('project_id', $foreign['columns'] ?? [], true)) {
                Schema::table('labours', function (Blueprint $table) use ($foreign): void {
                    $table->dropForeign($foreign['name']);
                });
            }
        }
        Schema::table('labours', function (Blueprint $table): void {
            $table->dropColumn('project_id');
        });
    }

    public function down(): void
    {
        // Reverting global identities to project-specific workers is not
        // safe automatically: no original project owner can be inferred.
        // Never invent project IDs or silently change existing payroll FKs.
        throw new \RuntimeException(
            'Global labour identity cannot be reverted without an explicit worker/project backfill plan.'
        );
    }
};
