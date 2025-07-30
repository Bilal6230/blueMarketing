<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{
    use HasFactory;
    protected $fillable = [
        'instalment_name',
        'is_active',
    ];

    public function bookingDetail()
    {
        return $this->belongsTo(BookingDetail::class);
    }
}
