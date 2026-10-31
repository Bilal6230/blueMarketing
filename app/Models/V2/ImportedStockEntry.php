<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Physical historical 'stock_entries' evidence in isolated V2 only.
 * Read-only via ImportedHistoricalModel, never a legacy type_id association.
 */
final class ImportedStockEntry extends ImportedHistoricalModel
{
    protected $table = 'stock_entries';

    protected function casts(): array
    {
        return [
            'entry_date' => 'immutable_date',
            'party_id' => 'integer',
            'billed_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ImportedStockEntryLine::class, 'entry_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(ImportedStockParty::class, 'party_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(ImportedStockBill::class, 'entry_id');
    }
}
