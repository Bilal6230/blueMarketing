<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Physical historical 'labour_ledgers' evidence in isolated V2 only.
 * Read-only via ImportedHistoricalModel, never a legacy type_id association.
 */
final class ImportedLabourLedger extends ImportedHistoricalModel
{
    protected $table = 'labour_ledgers';

    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'labour_id' => 'integer',
            'date' => 'immutable_date',
            'amount' => 'decimal:2',
        ];
    }

    public function labour(): BelongsTo
    {
        return $this->belongsTo(ImportedLabour::class, 'labour_id');
    }
}
