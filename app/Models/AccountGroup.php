<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountGroup extends Model
{
    protected $fillable = ['project_id', 'parent_id', 'code', 'name', 'description', 'is_active', 'create_by'];

    protected $casts = ['is_active' => 'boolean'];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function heads()
    {
        return $this->belongsToMany(HeadAccounting::class, 'project_head_account_groups', 'account_group_id', 'head_accounting_id')
            ->withPivot(['project_id', 'create_by'])->withTimestamps();
    }
}
