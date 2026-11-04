<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('permissions')) {
            $now = now();
            foreach (['read payroll', 'create payroll', 'approve payroll'] as $name) {
                DB::table('permissions')->insertOrIgnore([
                    'name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            if (Schema::hasTable('roles') && Schema::hasTable('role_has_permissions')) {
                $superadminId = DB::table('roles')->where('name', 'superadmin')->where('guard_name', 'web')->value('id');
                if ($superadminId) {
                    $permissionIds = DB::table('permissions')->whereIn('name', ['read payroll', 'create payroll', 'approve payroll'])->pluck('id');
                    foreach ($permissionIds as $permissionId) {
                        DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $superadminId]);
                    }
                }
            }
            if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            }
        }
        if (!Schema::hasTable('employee_profiles') || !Schema::hasTable('projects')) {
            throw new RuntimeException('Employee payroll snapshots require employee profiles.');
        }

        if (!Schema::hasTable('employee_payroll_runs')) {
            Schema::create('employee_payroll_runs', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->char('payroll_month', 7);
                $table->unsignedInteger('run_number');
                $table->string('status', 24)->default('pending_approval');
                $table->unsignedInteger('employee_count');
                $table->decimal('gross_total', 14, 2);
                $table->decimal('net_total', 14, 2);
                $table->char('snapshot_hash', 64);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->timestamps();
                $table->foreign('project_id', 'employee_payroll_run_project_fk')->references('id')->on('projects')->restrictOnDelete();
                $table->unique(['project_id', 'payroll_month', 'run_number'], 'employee_payroll_run_version_uq');
                $table->unique(['id', 'project_id'], 'employee_payroll_run_id_project_uq');
                $table->index(['project_id', 'payroll_month', 'status'], 'employee_payroll_run_lookup_idx');
            });
        }

        if (!Schema::hasTable('employee_payroll_run_items')) {
            Schema::create('employee_payroll_run_items', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('payroll_run_id');
                $table->unsignedBigInteger('employee_profile_id');
                $table->decimal('gross_amount', 14, 2);
                $table->decimal('net_amount', 14, 2);
                $table->json('snapshot_data');
                $table->char('source_hash', 64);
                $table->timestamp('created_at')->useCurrent();
                $table->foreign(['payroll_run_id', 'project_id'], 'employee_payroll_item_run_project_fk')
                    ->references(['id', 'project_id'])->on('employee_payroll_runs')->restrictOnDelete();
                $table->foreign(['employee_profile_id', 'project_id'], 'employee_payroll_item_profile_project_fk')
                    ->references(['id', 'project_id'])->on('employee_profiles')->restrictOnDelete();
                $table->unique(['payroll_run_id', 'employee_profile_id'], 'employee_payroll_item_employee_uq');
                $table->index(['project_id', 'employee_profile_id'], 'employee_payroll_item_project_employee_idx');
            });
        }

        if (!Schema::hasTable('employee_payroll_run_audits')) {
            Schema::create('employee_payroll_run_audits', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('payroll_run_id');
                $table->string('action', 32);
                $table->json('details')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->foreign(['payroll_run_id', 'project_id'], 'employee_payroll_audit_run_project_fk')
                    ->references(['id', 'project_id'])->on('employee_payroll_runs')->restrictOnDelete();
                $table->index(['project_id', 'payroll_run_id', 'created_at'], 'employee_payroll_audit_lookup_idx');
            });
        }
    }

    public function down(): void
    {
        // Payroll snapshots are accounting history and are retained on rollback.
    }
};
