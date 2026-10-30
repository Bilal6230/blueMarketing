<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** Physical imported account ID remains unsigned INT, never inferred from type_id. */
final class ImportedProjectAccount extends ImportedHistoricalModel
{
    protected $table = 'project_head_subheads';

    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'head_accounting_id' => 'integer',
            'subhead_accounting_id' => 'integer',
            'plot_id' => 'integer',
            'customer_id' => 'integer',
        ];
    }

    public function journalDetails(): HasMany
    {
        return $this->hasMany(ImportedJournalVoucherDetail::class, 'account_id');
    }

    public function legacyLedgerRows(): HasMany
    {
        return $this->hasMany(ImportedLedger::class, 'project_head_subheads_id');
    }
}
