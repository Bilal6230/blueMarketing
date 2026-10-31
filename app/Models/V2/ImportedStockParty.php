<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Physical historical 'stock_parties' evidence in isolated V2 only.
 * Read-only via ImportedHistoricalModel, never a legacy type_id association.
 */
final class ImportedStockParty extends ImportedHistoricalModel
{
    protected $table = 'stock_parties';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ImportedStockEntry::class, 'party_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(ImportedStockBill::class, 'party_id');
    }
}
