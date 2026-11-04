<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeEmploymentRevision extends Model
{
    protected $fillable = [
        'project_id', 'employee_profile_id', 'effective_from', 'basic_salary', 'allowances', 'payment_method',
        'salary_status', 'salary_proration_method', 'duty_start', 'duty_end', 'break_minutes', 'daily_hours', 'working_days_per_week',
        'weekly_off_days', 'grace_minutes', 'late_fine_policy', 'absence_deduction', 'half_day_after_minutes',
        'half_day_deduction', 'short_leave_minutes', 'short_leave_method', 'short_leave_deduction',
        'early_departure_method', 'early_departure_deduction', 'leave_deduction', 'overtime_method', 'overtime_rate',
        'other_adjustments', 'reason', 'changed_by',
    ];

    protected $casts = [
        'effective_from' => 'date', 'basic_salary' => 'decimal:2', 'allowances' => 'array',
        'daily_hours' => 'decimal:2', 'weekly_off_days' => 'array', 'late_fine_policy' => 'array',
        'absence_deduction' => 'decimal:2', 'leave_deduction' => 'decimal:2',
        'half_day_deduction' => 'decimal:2', 'short_leave_deduction' => 'decimal:2',
        'early_departure_deduction' => 'decimal:2',
        'overtime_rate' => 'decimal:2', 'other_adjustments' => 'array',
    ];

    public function employee() { return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id'); }
}
