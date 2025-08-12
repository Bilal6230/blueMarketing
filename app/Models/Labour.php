<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Labour extends Model
{
    use HasFactory;
     protected $fillable = [
        'name',
        'project_id',
        'cnic',
        'phone',
        'daily_wage',
        'join_date',
        'status',
    ];
}
