<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_employment_revisions')) {
            throw new RuntimeException('Run the employee setup migration before adding payroll policies.');
        }

        Schema::table('employee_employment_revisions', function (Blueprint $table): void {
            if (!Schema::hasColumn('employee_employment_revisions', 'salary_proration_method')) {
                $table->string('salary_proration_method', 24)->default('manual_review');
            }
            if (!Schema::hasColumn('employee_employment_revisions', 'half_day_deduction')) {
                $table->decimal('half_day_deduction', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('employee_employment_revisions', 'short_leave_method')) {
                $table->string('short_leave_method', 24)->nullable();
            }
            if (!Schema::hasColumn('employee_employment_revisions', 'short_leave_deduction')) {
                $table->decimal('short_leave_deduction', 12, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        // Keep payroll policy history when rolling back application code.
    }
};
