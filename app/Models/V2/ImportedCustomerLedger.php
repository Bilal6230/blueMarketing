<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Physical historical 'customer_ledger' evidence in isolated V2 only.
 * Read-only via ImportedHistoricalModel, never a legacy type_id association.
 */
final class ImportedCustomerLedger extends ImportedHistoricalModel
{
    protected $table = 'customer_ledger';

    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'customer_id' => 'integer',
            'plot_id' => 'integer',
            'type_id' => 'integer',
            'amount_in' => 'decimal:2',
            'amount_out' => 'decimal:2',
            'date' => 'immutable_date',
            'passing_date' => 'immutable_datetime',
            'bank_post_at' => 'immutable_datetime',
            'check_history' => 'array',
        ];
    }

    public function bookingVouchers(): HasMany
    {
        return $this->hasMany(ImportedBookingVoucher::class, 'customer_ledger_id');
    }

    public function legacyLedgerRows(): HasMany
    {
        return $this->hasMany(ImportedLedger::class, 'customer_ledger_id');
    }
}
