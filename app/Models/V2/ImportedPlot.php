<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** Read-only exact physical source 'plots' in isolated V2. */
final class ImportedPlot extends ImportedHistoricalModel
{
    protected $table = 'plots';

    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'is_active' => 'boolean',
            'sold' => 'boolean',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ImportedBooking::class, 'plot_id');
    }

    public function bookingVouchers(): HasMany
    {
        return $this->hasMany(ImportedBookingVoucher::class, 'plot_id');
    }
}
