<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    use HasFactory;

    protected $table = 'stock_items';

    protected $fillable = [
        'name',
        'unit',
        'default_rate_minor',
        'created_by',
    ];

    protected $casts = [
        'default_rate_minor' => 'integer',
        'created_by' => 'integer',
    ];

    public function balance()
    {
        return $this->hasOne(StockItemBalance::class, 'item_id', 'id');
    }
}
