<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Physical historical 'labours' evidence in isolated V2 only.
 * Read-only via ImportedHistoricalModel, never a legacy type_id association.
 */
final class ImportedLabour extends ImportedHistoricalModel
{
    protected $table = 'labours';

    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'daily_wage' => 'decimal:2',
            'join_date' => 'immutable_date',
        ];
    }

    public function ledgerRows(): HasMany
    {
        return $this->hasMany(ImportedLabourLedger::class, 'labour_id');
    }

    public function attendanceRows(): HasMany
    {
        return $this->hasMany(ImportedLabourAttendance::class, 'labour_id');
    }
}
