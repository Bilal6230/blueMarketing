<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $requiredColumns = [
            'opening_date', 'corner_charge_percent', 'facing_charge_percent', 'created_at',
        ];
        if (!Schema::hasTable('projects') || !Schema::hasTable('project_charge_revisions')) {
            return;
        }
        foreach ($requiredColumns as $column) {
            if (!Schema::hasColumn('projects', $column)) return;
        }

        // Preserve each existing project's current configured rates as its
        // first known baseline. Never replace or rewrite an existing revision.
        DB::table('projects')->orderBy('id')->chunkById(200, function ($projects): void {
            foreach ($projects as $project) {
                $exists = DB::table('project_charge_revisions')
                    ->where('project_id', $project->id)->exists();
                if ($exists) continue;

                $effectiveFrom = $project->opening_date
                    ?: ($project->created_at ? substr((string) $project->created_at, 0, 10) : now()->toDateString());

                DB::table('project_charge_revisions')->insertOrIgnore([
                    'project_id' => $project->id,
                    'corner_charge_percent' => $project->corner_charge_percent,
                    'facing_charge_percent' => $project->facing_charge_percent,
                    'effective_from' => $effectiveFrom,
                    'changed_by' => $project->create_by ?? null,
                    'created_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Baseline revisions are retained so a rollback cannot erase history.
    }
};
