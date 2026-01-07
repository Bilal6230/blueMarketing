<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Labour extends Model
{
    use HasFactory;
     protected $fillable = [
        'name',
        'father_name',
        'cnic',
        'phone',
        'daily_wage',
        'advance',
        'join_date',
        'role',
        'status',
    ];
    public function attendances()
    {
        return $this->hasMany(LabourAttendance::class)->where('project_id', getSelectedTown());
    }
    public function tAttendances()
    {
        return $this->hasMany(LabourAttendance::class)->where('status', 'present')->where('project_id', getSelectedTown());
    }
    public function labourLedgers()
    {
        return $this->hasMany(LabourLedger::class)->where('project_id', getSelectedTown());
    }
}
