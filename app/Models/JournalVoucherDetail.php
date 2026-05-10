<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalVoucherDetail extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'journal_voucher_details';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'journal_voucher_id',
        'account_id',
        'debit',
        'credit',
        'description',
    ];

    /**
     * Relationship: Belongs to JournalVoucher.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function journalVoucher()
    {
        return $this->belongsTo(JournalVoucher::class, 'journal_voucher_id');
    }

    /**
     * Relationship: Belongs to Account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo(ProjectHeadSubhead::class, 'account_id');
    }
}
