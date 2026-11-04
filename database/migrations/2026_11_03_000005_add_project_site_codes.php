<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sites')) {
            return;
        }

        if (!Schema::hasColumn('sites', 'project_id')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->unsignedBigInteger('project_id')->nullable();
                $table->foreign('project_id', 'sites_project_id_fk')
                    ->references('id')->on('projects')->restrictOnDelete();
            });
        }

        if (!Schema::hasColumn('sites', 'site_code')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->string('site_code', 40)->nullable();
            });
        }

        if (!Schema::hasColumn('sites', 'is_active')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->boolean('is_active')->default(true);
            });
        }

        // Legacy rows deliberately keep their current identity and scope.
        Schema::table('sites', function (Blueprint $table) {
            $table->unique(['project_id', 'site_code'], 'sites_project_site_code_unique');
        });
    }

    public function down(): void
    {
        // Preserve site identifiers and project relationships on rollback.
    }
};
