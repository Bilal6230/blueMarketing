<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockParty extends Model
{
    use HasFactory;

    protected $table = 'stock_parties';

    protected $fillable = [
        'type',      // Supplier | Consumer | Site
        'name',
        'mobile',
        'address',
        'head_account',
        'sub_head_account',
        'created_by',
    ];

    protected $casts = [
        'created_by' => 'integer',
    ];
}
