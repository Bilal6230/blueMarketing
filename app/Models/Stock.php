<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory;
    protected $fillable = [
        'stock_name',
        'total_remaining',
        'project_id',
        'quantity',
        'type',
        'total_used',
    ];
}
