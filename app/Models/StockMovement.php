<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $table = 'stock_movements';

    protected $fillable = [
        'movement_date',
        'direction',    // IN | OUT
        'item_id',
        'qty',
        'rate_minor',
        'amount_minor',
        'source_type',  // entry | bill
        'source_id',
        'source_line_id',
        'party_id',
        'meta',
        'created_by',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'item_id' => 'integer',
        'rate_minor' => 'integer',
        'amount_minor' => 'integer',
        'source_id' => 'integer',
        'source_line_id' => 'integer',
        'party_id' => 'integer',
        'meta' => 'array',
        'created_by' => 'integer',
    ];

    public function item()
    {
        return $this->belongsTo(StockItem::class, 'item_id', 'id');
    }
}
