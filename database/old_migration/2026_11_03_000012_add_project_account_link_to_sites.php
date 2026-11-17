<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sites') || !Schema::hasTable('project_head_subheads')) {
            throw new LogicException('Site accounting links require sites and project_head_subheads.');
        }

        if (!Schema::hasIndex('project_head_subheads', 'project_head_subheads_id_project_unique')) {
            Schema::table('project_head_subheads', function (Blueprint $table): void {
                $table->unique(['id', 'project_id'], 'project_head_subheads_id_project_unique');
            });
        }

        if (!Schema::hasColumn('sites', 'project_head_subhead_id')) {
            Schema::table('sites', function (Blueprint $table): void {
                $table->unsignedBigInteger('project_head_subhead_id')->nullable()->index('sites_project_account_idx');
                $table->foreign(['project_head_subhead_id', 'project_id'], 'sites_project_account_fk')
                    ->references(['id', 'project_id'])->on('project_head_subheads')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Preserve site accounting assignments on rollback.
    }
};
