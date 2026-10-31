<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Physical historical 'booking_vouchers' evidence in isolated V2 only.
 * Read-only via ImportedHistoricalModel, never a legacy type_id association.
 */
final class ImportedBookingVoucher extends ImportedHistoricalModel
{
    protected $table = 'booking_vouchers';

    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'booking_id' => 'integer',
            'customer_ledger_id' => 'integer',
            'ledger_id' => 'integer',
            'voucher_number' => 'integer',
            'amount' => 'decimal:2',
            'receipt_date' => 'immutable_date',
            'passing_date' => 'immutable_date',
            'is_active' => 'boolean',
            'is_approve' => 'boolean',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(ImportedBooking::class, 'booking_id');
    }

    public function customerLedger(): BelongsTo
    {
        return $this->belongsTo(ImportedCustomerLedger::class, 'customer_ledger_id');
    }

    public function legacyLedger(): BelongsTo
    {
        return $this->belongsTo(ImportedLedger::class, 'ledger_id');
    }
}
