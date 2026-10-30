<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Canonical source family discriminator, not polymorphic legacy type_id. */
final class FinancePostingSourceClaim extends Model
{
    protected $table = 'finance_posting_source_claims';
    protected $primaryKey = 'posting_batch_id';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';
    protected $guarded = ['*'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(FinancePostingBatch::class, 'posting_batch_id');
    }
}
