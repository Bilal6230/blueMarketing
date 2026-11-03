<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceDeviceUserMapping extends Model
{
    protected $fillable = ['attendance_device_id', 'user_id', 'device_user_id'];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
