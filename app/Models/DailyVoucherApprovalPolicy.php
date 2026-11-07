<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyVoucherApprovalPolicy extends Model
{
    protected $fillable = ['project_id', 'voucher_type', 'approval_required', 'approver_user_id', 'updated_by'];

    protected $casts = ['approval_required' => 'boolean'];
}
