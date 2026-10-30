<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Exact original 15-column journal_vouchers source. In particular source
 * table has NO site_id; existing V1 model remains unchanged for legacy UI.
 */
final class ImportedJournalVoucher extends ImportedHistoricalModel
{
    protected $table = 'journal_vouchers';

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'voucher_number' => 'integer',
            'date' => 'immutable_date',
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'project_id' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function details(): HasMany
    {
        return $this->hasMany(ImportedJournalVoucherDetail::class, 'journal_voucher_id');
    }
}
