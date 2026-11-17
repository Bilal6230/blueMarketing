<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('projects')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'project_code')) $table->string('project_code', 40)->nullable();
            if (!Schema::hasColumn('projects', 'location')) $table->string('location', 255)->nullable();
            if (!Schema::hasColumn('projects', 'opening_date')) $table->date('opening_date')->nullable();
            if (!Schema::hasColumn('projects', 'closing_date')) $table->date('closing_date')->nullable();
            if (!Schema::hasColumn('projects', 'corner_charge_percent')) $table->decimal('corner_charge_percent', 5, 2)->default(0);
            if (!Schema::hasColumn('projects', 'facing_charge_percent')) $table->decimal('facing_charge_percent', 5, 2)->default(0);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->unique('project_code', 'projects_project_code_unique');
        });

        if (!Schema::hasTable('project_charge_revisions')) {
            Schema::create('project_charge_revisions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->decimal('corner_charge_percent', 5, 2);
                $table->decimal('facing_charge_percent', 5, 2);
                $table->date('effective_from');
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->foreign('project_id', 'project_charge_revisions_project_fk')
                    ->references('id')->on('projects')->restrictOnDelete();
                $table->unique(['project_id', 'effective_from'], 'project_charge_revision_effective_unique');
            });
        }
    }

    public function down(): void
    {
        // Keep project identifiers and rate history across application rollback.
    }
};
