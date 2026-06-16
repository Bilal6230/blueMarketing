<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ledger extends Model
{
    use HasFactory, SoftDeletes;
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

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

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
