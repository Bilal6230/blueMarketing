<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockBillLine extends Model
{
    use HasFactory;

    protected $table = 'stock_bill_lines';

    protected $fillable = [
        'bill_id',
        'item_id',
        'qty',
        'rate_minor',
        'amount_minor',
    ];

    protected $casts = [
        'bill_id' => 'integer',
        'item_id' => 'integer',
        'rate_minor' => 'integer',
        'amount_minor' => 'integer',
    ];

    public function bill()
    {
        return $this->belongsTo(StockBill::class, 'bill_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(StockItem::class, 'item_id', 'id');
    }
}
