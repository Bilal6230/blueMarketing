<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** Read-only exact physical source 'leads' in isolated V2. */
final class ImportedLead extends ImportedHistoricalModel
{
    protected $table = 'leads';

    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'is_active' => 'boolean',
            'follow_up' => 'immutable_datetime',
            'customer_type' => 'integer',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ImportedBooking::class, 'customer_id');
    }

    public function customerLedgers(): HasMany
    {
        return $this->hasMany(ImportedCustomerLedger::class, 'customer_id');
    }
}
