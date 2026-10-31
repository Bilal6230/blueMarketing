<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Physical historical 'stock_bills' evidence in isolated V2 only.
 * Read-only via ImportedHistoricalModel, never a legacy type_id association.
 */
final class ImportedStockBill extends ImportedHistoricalModel
{
    protected $table = 'stock_bills';

    protected function casts(): array
    {
        return [
            'bill_date' => 'immutable_date',
            'party_id' => 'integer',
            'entry_id' => 'integer',
            'total_minor' => 'integer',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ImportedStockBillLine::class, 'bill_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(ImportedStockParty::class, 'party_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(ImportedStockEntry::class, 'entry_id');
    }
}
