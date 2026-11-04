<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAttendanceRecord extends Model
{
    protected $fillable = [
        'project_id', 'employee_profile_id', 'user_id', 'work_date', 'snapshot_batch_id', 'status', 'punch_in', 'punch_out',
        'source_punch_ids', 'source_snapshot', 'snapshot_by', 'reviewed_by', 'reviewed_at', 'review_reason',
    ];

    protected $casts = [
        'work_date' => 'date', 'punch_in' => 'datetime', 'punch_out' => 'datetime',
        'source_punch_ids' => 'array', 'source_snapshot' => 'array', 'reviewed_at' => 'datetime',
    ];

    public function employee() { return $this->belongsTo(EmployeeProfile::class, 'employee_profile_id'); }
    public function user() { return $this->belongsTo(User::class)->withTrashed(); }
}
