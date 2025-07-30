<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use HasFactory;

    protected $fillable = [
        'zone_name',
        'is_active',
        'create_by',

    ];

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'projects_zones');
    }


}
