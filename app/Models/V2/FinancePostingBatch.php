<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Isolated V2 finance schema model; no legacy posting controller uses this. */
final class FinancePostingBatch extends Model
{
    protected $table = 'finance_posting_batches';
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'voucher_number' => 'integer',
            'effective_date' => 'immutable_date',
            'posted_at' => 'immutable_datetime',
            'posted_by' => 'integer',
            'reversal_of_batch_id' => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FinancePostingLine::class, 'posting_batch_id');
    }

    public function originalBatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_batch_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_batch_id');
    }

    public function sourceClaim(): HasOne
    {
        return $this->hasOne(FinancePostingSourceClaim::class, 'posting_batch_id');
    }
}
