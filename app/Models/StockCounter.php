<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockCounter extends Model
{
    use HasFactory;

    protected $table = 'stock_counters';

    protected $fillable = [
        'key',
        'next_number',
    ];

    protected $casts = [
        'next_number' => 'integer',
    ];
}
