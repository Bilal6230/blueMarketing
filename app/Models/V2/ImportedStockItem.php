<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Physical historical 'stock_items' evidence in isolated V2 only.
 * Read-only via ImportedHistoricalModel, never a legacy type_id association.
 */
final class ImportedStockItem extends ImportedHistoricalModel
{
    protected $table = 'stock_items';

    protected function casts(): array
    {
        return [
            'default_rate_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function entryLines(): HasMany
    {
        return $this->hasMany(ImportedStockEntryLine::class, 'item_id');
    }

    public function billLines(): HasMany
    {
        return $this->hasMany(ImportedStockBillLine::class, 'item_id');
    }
}
