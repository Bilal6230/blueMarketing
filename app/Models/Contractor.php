<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contractor extends Model
{
    protected $fillable = ['project_id', 'code', 'name', 'phone', 'address', 'project_head_subhead_id', 'is_active', 'changed_by'];

    protected $casts = ['is_active' => 'boolean'];

    public function account()
    {
        return $this->belongsTo(ProjectHeadSubhead::class, 'project_head_subhead_id');
    }
}
