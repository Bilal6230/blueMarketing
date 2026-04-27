<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingVoucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'customer_ledger_id',
        'ledger_id',
        'project_id',
        'customer_id',
        'plot_id',
        'voucher_series',
        'voucher_number',
        'slip_reference',
        'payment_type',
        'amount',
        'receipt_date',
        'description',
        'bank_id',
        't_number',
        'passing_date',
        'is_active',
        'is_approve',
        'create_by',
        'update_by',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function customerLedger()
    {
        return $this->belongsTo(CustomerLedger::class);
    }

    public function customer()
    {
        return $this->belongsTo(Lead::class, 'customer_id');
    }

    public function ledger()
    {
        return $this->belongsTo(Ledger::class);
    }

    public function plot()
    {
        return $this->belongsTo(Plot::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
