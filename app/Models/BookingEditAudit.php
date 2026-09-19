<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingEditAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id', 'project_id', 'user_id', 'operation', 'reason',
        'old_values', 'new_values', 'old_total', 'new_total', 'delta',
        'old_accounting_principal', 'delta_from_accounting',
        'journal_voucher_id', 'request_key',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];
}
