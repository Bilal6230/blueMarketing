<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyVoucherApproval extends Model
{
    protected $fillable = [
        'project_id', 'voucher_type', 'source_table', 'source_id', 'status',
        'assigned_approver_id', 'submitted_by', 'decided_by', 'rejection_reason', 'decided_at',
    ];

    protected $casts = ['decided_at' => 'datetime'];

    public function assignedApprover() { return $this->belongsTo(User::class, 'assigned_approver_id'); }
    public function submitter() { return $this->belongsTo(User::class, 'submitted_by'); }
    public function events() { return $this->hasMany(DailyVoucherApprovalEvent::class, 'approval_id')->orderBy('id'); }
}
