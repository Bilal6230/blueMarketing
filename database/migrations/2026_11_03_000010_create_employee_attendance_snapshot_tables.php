<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_profiles') || !Schema::hasTable('projects')) {
            throw new RuntimeException('Employee attendance requires the employee profile setup migration.');
        }

        if (!Schema::hasTable('employee_attendance_records')) {
            Schema::create('employee_attendance_records', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('employee_profile_id');
                $table->unsignedBigInteger('user_id');
                $table->date('work_date');
                $table->string('snapshot_batch_id', 36);
                $table->string('status', 24)->default('unreviewed');
                $table->dateTime('punch_in')->nullable();
                $table->dateTime('punch_out')->nullable();
                $table->json('source_punch_ids')->nullable();
                $table->json('source_snapshot')->nullable();
                $table->unsignedBigInteger('snapshot_by')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->dateTime('reviewed_at')->nullable();
                $table->string('review_reason', 500)->nullable();
                $table->timestamps();
                $table->foreign('project_id', 'employee_att_project_fk')->references('id')->on('projects')->restrictOnDelete();
                $table->foreign(['employee_profile_id', 'user_id', 'project_id'], 'employee_att_profile_user_project_fk')
                    ->references(['id', 'user_id', 'project_id'])->on('employee_profiles')->restrictOnDelete();
                $table->unique(['employee_profile_id', 'work_date'], 'employee_att_profile_day_uq');
                $table->index(['project_id', 'work_date', 'status'], 'employee_att_project_day_status_idx');
                $table->index(['project_id', 'snapshot_batch_id'], 'employee_att_snapshot_batch_idx');
            });
        }

        if (!Schema::hasTable('employee_attendance_audits')) {
            Schema::create('employee_attendance_audits', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('employee_attendance_record_id');
                $table->string('action', 32);
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('reason', 500)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->foreign(['employee_attendance_record_id', 'project_id'], 'employee_att_audit_scope_fk')
                    ->references(['id', 'project_id'])->on('employee_attendance_records')->restrictOnDelete();
                $table->index(['employee_attendance_record_id', 'created_at'], 'employee_att_audit_record_created_idx');
                $table->index(['project_id', 'created_at'], 'employee_att_audit_project_created_idx');
            });
        }
    }

    public function down(): void
    {
        // Attendance snapshots and their audit trail are retained on rollback.
    }
};
