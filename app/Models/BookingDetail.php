<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'installment_details',
        'amount',
        'due_date',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function installment()
    {
        return $this->belongsTo(Installment::class);
    }
}
