<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectCashAccount extends Model
{
    protected $fillable = ['project_id','account_mapping_id','display_title','is_active','created_by','updated_by'];
    protected $casts = ['is_active'=>'boolean'];

    public function accountMapping()
    {
        return $this->belongsTo(ProjectHeadSubhead::class,'account_mapping_id');
    }
}
