<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Physical historical 'stock_entry_lines' evidence in isolated V2 only.
 * Read-only via ImportedHistoricalModel, never a legacy type_id association.
 */
final class ImportedStockEntryLine extends ImportedHistoricalModel
{
    protected $table = 'stock_entry_lines';

    protected function casts(): array
    {
        return [
            'entry_id' => 'integer',
            'item_id' => 'integer',
            'qty' => 'decimal:3',
            'rate_minor' => 'integer',
            'amount_minor' => 'integer',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(ImportedStockEntry::class, 'entry_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ImportedStockItem::class, 'item_id');
    }
}
