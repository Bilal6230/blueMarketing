<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectBankAccount extends Model
{
    protected $fillable = [
        'project_id', 'account_mapping_id', 'bank_name', 'account_title', 'branch', 'account_number',
        'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = ['account_number' => 'encrypted', 'is_active' => 'boolean'];

    public function project() { return $this->belongsTo(Project::class); }
    public function accountMapping() { return $this->belongsTo(ProjectHeadSubhead::class, 'account_mapping_id'); }
}
