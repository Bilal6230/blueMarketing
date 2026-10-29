<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ledger extends Model
{
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
        'voucher_number'
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
    }

    public function projectHeadSubhead()
    {
        return $this->belongsTo(ProjectHeadSubhead::class, 'project_head_subheads_id');
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'create_by', 'id');
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
