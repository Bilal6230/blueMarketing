<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DraftLedger extends Model
{
    use HasFactory;
    protected $fillable = [
        // Customer Ledger Fields
        'date',
        'reference',
        'type_id',
        'customer_id',
        'project_id',
        'plot_id',
        'payment_type',
        't_number',
        'bank_id',
        'passing_date',
        'passing_status',
        'check_history',
        'transaction_type',
        'amount_in',
        'amount_out',
        'description',
        'is_active',
        'is_approve',
        'note',
        'bank_post_at',

        // Ledger Fields
        'type',
        'project_head_subheads_id',
        'detail',
        'create_by',
        'update_by',
        'status',
    ];
}
