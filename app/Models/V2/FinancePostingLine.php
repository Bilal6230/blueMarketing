<?php

namespace App\Models\V2;

use App\Models\ProjectHeadSubhead;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DECIMAL values are always strings; never use float casts for money. */
final class FinancePostingLine extends Model
{
    protected $table = 'finance_posting_lines';
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'posting_batch_id' => 'integer',
            'project_head_subhead_id' => 'integer',
            'legacy_ledger_id' => 'integer',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(FinancePostingBatch::class, 'posting_batch_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ProjectHeadSubhead::class, 'project_head_subhead_id')->withTrashed();
    }
}
