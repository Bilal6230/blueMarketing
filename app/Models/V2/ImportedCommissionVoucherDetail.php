<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Physical historical 'commision_voucher_details' evidence in isolated V2 only.
 * Read-only via ImportedHistoricalModel, never a legacy type_id association.
 */
final class ImportedCommissionVoucherDetail extends ImportedHistoricalModel
{
    protected $table = 'commision_voucher_details';

    protected function casts(): array
    {
        return [
            'commision_voucher_id' => 'integer',
            'account_id' => 'integer',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function commissionVoucher(): BelongsTo
    {
        return $this->belongsTo(ImportedCommissionVoucher::class, 'commision_voucher_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ImportedProjectAccount::class, 'account_id');
    }
}
