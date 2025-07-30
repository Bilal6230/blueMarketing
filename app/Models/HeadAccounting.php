<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeadAccounting extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
        'acct_type',
        'create_by',
    ];

    public function subheadAccountings()
    {
        return $this->hasMany(SubheadAccounting::class);
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_head_subheads');
    }
}
