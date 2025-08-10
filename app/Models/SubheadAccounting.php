<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubheadAccounting extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'is_active',
        'create_by',
        'cnic',
        'phone',
    ];
    public function headAccounting()
    {
        return $this->belongsTo(HeadAccounting::class);
    }
    public function headAccountings()
    {
        return $this->belongsToMany(HeadAccounting::class, 'project_head_subheads');
    }
}
