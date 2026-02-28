<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockEntryLine extends Model
{
    use HasFactory;

    protected $table = 'stock_entry_lines';

    protected $fillable = [
        'entry_id',
        'item_id',
        'qty',
        'rate_minor',
        'amount_minor',
    ];

    protected $casts = [
        'entry_id' => 'integer',
        'item_id' => 'integer',
        'rate_minor' => 'integer',
        'amount_minor' => 'integer',
    ];

    public function entry()
    {
        return $this->belongsTo(StockEntry::class, 'entry_id', 'id');
    }

    public function item()
    {
        return $this->belongsTo(StockItem::class, 'item_id', 'id');
    }
}
