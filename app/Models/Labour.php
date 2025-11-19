<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Labour extends Model
{
    use HasFactory;
     protected $fillable = [
        'name',
        'cnic',
        'phone',
        'daily_wage',
        'join_date',
        'role',
        'status',
    ];
    public function attendances()
    {
        return $this->hasMany(LabourAttendance::class);
    }
}
