<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Exact original JV detail FK mapping; never a polymorphic voucher type_id. */
final class ImportedJournalVoucherDetail extends ImportedHistoricalModel
{
    protected $table = 'journal_voucher_details';

    protected function casts(): array
    {
        return [
            'journal_voucher_id' => 'integer',
            'account_id' => 'integer',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function journalVoucher(): BelongsTo
    {
        return $this->belongsTo(ImportedJournalVoucher::class, 'journal_voucher_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ImportedProjectAccount::class, 'account_id');
    }
}
