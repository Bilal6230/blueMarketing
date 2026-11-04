<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDefinition extends Model
{
    protected $fillable = ['project_id', 'kind', 'code', 'name', 'is_active', 'created_by', 'updated_by'];

    protected $casts = ['is_active' => 'boolean'];

}
