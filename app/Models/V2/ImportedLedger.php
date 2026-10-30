<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Exact imported 19-column V1 ledger evidence, deliberately NOT a V2
 * posting identity: legacy type/type_id/reference remain opaque attributes.
 * Source table has NO journal_voucher_detail_id. Do not infer joins.
 */
final class ImportedLedger extends ImportedHistoricalModel
{
    protected $table = 'ledgers';

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'voucher_number' => 'integer',
            'customer_ledger_id' => 'integer',
            'charge_type_id' => 'integer',
            'type_id' => 'integer',
            'project_head_subheads_id' => 'integer',
            'amount_in' => 'decimal:2',
            'amount_out' => 'decimal:2',
            'create_by' => 'integer',
            'update_by' => 'integer',
            'is_active' => 'boolean',
            'date' => 'immutable_date',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function projectAccount(): BelongsTo
    {
        return $this->belongsTo(ImportedProjectAccount::class, 'project_head_subheads_id');
    }
}
