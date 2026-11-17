<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['sites', 'project_head_subheads'] as $table) {
            if (!Schema::hasTable($table)) return;
        }
        foreach (['project_id', 'head_accounting_id', 'subhead_accounting_id', 'project_head_subhead_id'] as $column) {
            if (!Schema::hasColumn('sites', $column)) return;
        }

        // Backfill only a unique project/head/subhead match. Ambiguous and
        // missing mappings remain untouched for explicit administrator repair.
        DB::table('sites')->whereNull('project_head_subhead_id')->orderBy('id')->chunkById(200, function ($sites): void {
            foreach ($sites as $site) {
                $matches = DB::table('project_head_subheads')->where('project_id', $site->project_id)
                    ->where('head_accounting_id', $site->head_accounting_id)
                    ->where('subhead_accounting_id', $site->subhead_accounting_id)
                    ->limit(2)->pluck('id');
                if ($matches->count() !== 1) continue;

                DB::table('sites')->where('id', $site->id)->whereNull('project_head_subhead_id')
                    ->update(['project_head_subhead_id' => $matches->first()]);
            }
        });
    }

    public function down(): void
    {
        // Retain unambiguous assignments so rollback cannot remove site history.
    }
};
