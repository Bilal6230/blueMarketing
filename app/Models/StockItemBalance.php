<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockItemBalance extends Model
{
    use HasFactory;

    protected $table = 'stock_item_balances';

    protected $fillable = [
        'item_id',
        'on_hand_qty',
    ];

    protected $casts = [
        'item_id' => 'integer',
    ];

    public function item()
    {
        return $this->belongsTo(StockItem::class, 'item_id', 'id');
    }
}
