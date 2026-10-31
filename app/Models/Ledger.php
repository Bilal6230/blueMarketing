<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ledger extends Model
{
    use \App\Models\Concerns\ProtectsFinancialHistory;
    use HasFactory;
    protected $table = 'ledgers';

    protected $fillable = [
        'type',
        'type_id',
        'project_head_subheads_id',
        'customer_ledger_id',
        'reference',
        'amount_in',
        'amount_out',
        'detail',
        'create_by',
        'update_by',
        'is_active',
        'status',
        'date',
        'delete_reason',
        'voucher_number',
        'journal_voucher_detail_id'
    ];

    protected static function booted(): void
    {
        static::creating(function (self $ledger): void {
            // Enforce ownership on every new explicitly source-linked CR, CP,
            // PPR, EC, ECP ledger leg. Historical writes remain untouched.
            if (in_array((string) $ledger->type,
                \App\Services\Finance\FinancialSourceOwnership::FAMILIES, true) &&
                $ledger->customer_ledger_id !== null) {
                app(\App\Services\Finance\FinancialSourceOwnership::class)
                    ->assertLinked($ledger->getAttributes());
            }
        });

        static::updating(function (self $ledger): void {
            // Defence in depth: controllers, jobs and future code using the
            // model cannot renumber or reassign a posted accounting leg.
            // A narrative correction is the only in-place change permitted.
            $allowed = ['detail', 'update_by', 'updated_at'];
            $forbidden = array_diff(array_keys($ledger->getDirty()), $allowed);
            if ($forbidden !== []) {
                throw new \DomainException('FINANCIAL_HISTORY_UPDATE_REQUIRES_REVERSAL');
            }
        });
    }

    public function projectHeadSubhead()
    {
        return $this->belongsTo(ProjectHeadSubhead::class, 'project_head_subheads_id')->withTrashed();
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'create_by', 'id')->withTrashed();
    }
    public function customerLedger()
    {
        return $this->belongsTo(CustomerLedger::class);
    }

    public function bookingVoucher()
    {
        return $this->hasOne(BookingVoucher::class, 'ledger_id', 'id');
    }
}
