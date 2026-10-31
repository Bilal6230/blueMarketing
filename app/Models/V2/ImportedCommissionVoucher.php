<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Physical historical 'commision_vouchers' evidence in isolated V2 only.
 * Read-only via ImportedHistoricalModel, never a legacy type_id association.
 */
final class ImportedCommissionVoucher extends ImportedHistoricalModel
{
    protected $table = 'commision_vouchers';

    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'date' => 'immutable_date',
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(ImportedCommissionVoucherDetail::class, 'commision_voucher_id');
    }
}
