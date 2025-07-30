<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plot extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'size',
        'unit',
        'description',
        'is_corner',
        'project_id',
        'road_id',
        'facing_id',
        'is_active',
        'sold',
        'plot_history',
        'create_by',

    ];
}
