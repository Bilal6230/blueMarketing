<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Physical historical 'labour_attendances' evidence in isolated V2 only.
 * Read-only via ImportedHistoricalModel, never a legacy type_id association.
 */
final class ImportedLabourAttendance extends ImportedHistoricalModel
{
    protected $table = 'labour_attendances';

    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'labour_id' => 'integer',
            'site_id' => 'integer',
            'date' => 'immutable_date',
            'hours' => 'decimal:2',
            'ot_hours' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'ratings' => 'decimal:1',
            'is_approved' => 'boolean',
        ];
    }

    public function labour(): BelongsTo
    {
        return $this->belongsTo(ImportedLabour::class, 'labour_id');
    }
}
