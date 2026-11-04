<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'site_code',
        'site_name',
        'site_address',
        'head_accounting_id',
        'subhead_accounting_id',
        'project_head_subhead_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
