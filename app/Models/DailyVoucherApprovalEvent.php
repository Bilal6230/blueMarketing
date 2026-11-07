<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyVoucherApprovalEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['approval_id', 'event', 'actor_id', 'remarks', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];
}
