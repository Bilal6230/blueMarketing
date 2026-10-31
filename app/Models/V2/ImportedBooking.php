<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Physical historical 'bookings' evidence in isolated V2 only.
 * Read-only via ImportedHistoricalModel, never a legacy type_id association.
 */
final class ImportedBooking extends ImportedHistoricalModel
{
    protected $table = 'bookings';

    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'customer_id' => 'integer',
            'plot_id' => 'integer',
            'plot_rate' => 'decimal:2',
            'park_facing' => 'decimal:2',
            'carner_price' => 'decimal:2',
            'dicount_value' => 'decimal:2',
            'total_price' => 'decimal:2',
            'cancel_sale_amount' => 'decimal:2',
            'cancel_adjustment_amount' => 'decimal:2',
            'cancel_net_amount' => 'decimal:2',
            'booking_date' => 'immutable_datetime',
            'cancel_posted_at' => 'immutable_date',
        ];
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(ImportedBookingVoucher::class, 'booking_id');
    }
}
