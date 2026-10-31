<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Read-only exact physical source 'stock_item_balances' in isolated V2. */
final class ImportedStockItemBalance extends ImportedHistoricalModel
{
    protected $table = 'stock_item_balances';
    protected $primaryKey = 'item_id';
    public $incrementing = false;


    protected function casts(): array
    {
        return [
            'item_id' => 'integer',
            'on_hand_qty' => 'decimal:3',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ImportedStockItem::class, 'item_id');
    }
}
