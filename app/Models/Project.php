<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'project',
        'address',
        'logo',
        'is_active',
        'create_by',

    ];

    public function zones()
    {
        return $this->belongsToMany(Zone::class, 'project_zones');
    }
    public function headAccountings()
    {
        return $this->belongsToMany(HeadAccounting::class, 'project_head_subheads');
    }
}
