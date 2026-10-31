<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Physical historical 'stock_bill_lines' evidence in isolated V2 only.
 * Read-only via ImportedHistoricalModel, never a legacy type_id association.
 */
final class ImportedStockBillLine extends ImportedHistoricalModel
{
    protected $table = 'stock_bill_lines';

    protected function casts(): array
    {
        return [
            'bill_id' => 'integer',
            'item_id' => 'integer',
            'qty' => 'decimal:3',
            'rate_minor' => 'integer',
            'amount_minor' => 'integer',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(ImportedStockBill::class, 'bill_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ImportedStockItem::class, 'item_id');
    }
}
