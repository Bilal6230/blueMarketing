<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('projects') || !Schema::hasTable('users') || !Schema::hasColumn('users', 'project_id')) {
            throw new RuntimeException('Employee setup requires projects and users.project_id.');
        }

        if (!Schema::hasTable('employee_definitions')) {
            Schema::create('employee_definitions', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->enum('kind', ['department', 'designation', 'category']);
                $table->string('code', 40);
                $table->string('name', 120);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->foreign('project_id', 'employee_definitions_project_fk')->references('id')->on('projects')->restrictOnDelete();
                $table->unique(['project_id', 'kind', 'code'], 'employee_definition_project_kind_code_uq');
                $table->unique(['id', 'project_id'], 'employee_definition_id_project_uq');
                $table->index(['project_id', 'kind', 'is_active'], 'employee_definition_lookup_idx');
            });
        }

        if (!Schema::hasTable('employee_profiles')) {
            Schema::create('employee_profiles', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('user_id');
                $table->string('employee_code', 40);
                $table->date('joining_date')->nullable();
                $table->unsignedBigInteger('department_definition_id')->nullable();
                $table->unsignedBigInteger('designation_definition_id')->nullable();
                $table->unsignedBigInteger('employee_category_definition_id')->nullable();
                $table->timestamps();
                $table->foreign('project_id', 'employee_profiles_project_fk')->references('id')->on('projects')->restrictOnDelete();
                $table->foreign('user_id', 'employee_profiles_user_fk')->references('id')->on('users')->restrictOnDelete();
                foreach (['department_definition_id', 'designation_definition_id', 'employee_category_definition_id'] as $column) {
                    $table->foreign([$column, 'project_id'], $column.'_project_fk')
                        ->references(['id', 'project_id'])->on('employee_definitions')->restrictOnDelete();
                }
                $table->unique('user_id', 'employee_profiles_user_uq');
                $table->unique(['project_id', 'employee_code'], 'employee_profiles_project_code_uq');
                $table->unique(['id', 'project_id'], 'employee_profiles_id_project_uq');
                $table->unique(['id', 'user_id', 'project_id'], 'employee_profiles_id_user_project_uq');
                $table->index(['project_id', 'joining_date'], 'employee_profiles_joining_idx');
            });
        }

        if (!Schema::hasTable('employee_employment_revisions')) {
            Schema::create('employee_employment_revisions', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('employee_profile_id');
                $table->date('effective_from');
                $table->decimal('basic_salary', 12, 2)->default(0);
                $table->json('allowances')->nullable();
                $table->string('payment_method', 40)->nullable();
                $table->enum('salary_status', ['active', 'hold'])->default('active');
                $table->time('duty_start')->nullable();
                $table->time('duty_end')->nullable();
                $table->unsignedSmallInteger('break_minutes')->default(0);
                $table->decimal('daily_hours', 5, 2)->nullable();
                $table->unsignedTinyInteger('working_days_per_week')->nullable();
                $table->json('weekly_off_days')->nullable();
                $table->unsignedSmallInteger('grace_minutes')->default(0);
                $table->json('late_fine_policy')->nullable();
                $table->decimal('absence_deduction', 12, 2)->default(0);
                $table->decimal('leave_deduction', 12, 2)->default(0);
                $table->unsignedSmallInteger('half_day_after_minutes')->nullable();
                $table->unsignedSmallInteger('short_leave_minutes')->nullable();
                $table->string('early_departure_method', 30)->nullable();
                $table->decimal('early_departure_deduction', 12, 2)->default(0);
                $table->string('overtime_method', 30)->nullable();
                $table->decimal('overtime_rate', 12, 2)->default(0);
                $table->json('other_adjustments')->nullable();
                $table->string('reason', 500);
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamps();
                $table->foreign('project_id', 'employment_revisions_project_fk')->references('id')->on('projects')->restrictOnDelete();
                $table->foreign(['employee_profile_id', 'project_id'], 'employment_revisions_profile_project_fk')
                    ->references(['id', 'project_id'])->on('employee_profiles')->restrictOnDelete();
                $table->unique(['employee_profile_id', 'effective_from'], 'employment_revision_effective_uq');
                $table->index(['project_id', 'effective_from'], 'employment_revision_project_date_idx');
            });
        }

        if (!Schema::hasTable('employee_definition_audits')) {
            Schema::create('employee_definition_audits', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('employee_definition_id');
                $table->string('action', 30);
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('reason', 500)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->foreign(['employee_definition_id', 'project_id'], 'employee_definition_audit_scope_fk')
                    ->references(['id', 'project_id'])->on('employee_definitions')->restrictOnDelete();
                $table->index(['project_id', 'employee_definition_id', 'created_at'], 'employee_definition_audit_idx');
            });
        }

        if (!Schema::hasTable('employee_profile_audits')) {
            Schema::create('employee_profile_audits', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('employee_profile_id');
                $table->json('old_values')->nullable();
                $table->json('new_values');
                $table->string('reason', 500);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->foreign(['employee_profile_id', 'project_id'], 'employee_profile_audit_scope_fk')
                    ->references(['id', 'project_id'])->on('employee_profiles')->restrictOnDelete();
                $table->index(['project_id', 'employee_profile_id', 'created_at'], 'employee_profile_audit_idx');
            });
        }
    }

    public function down(): void
    {
        // Retain employment histories and definition references during rollback.
    }
};
