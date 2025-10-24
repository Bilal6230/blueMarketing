<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendingUpdate extends Model
{
    use HasFactory;
    protected $fillable = [
        'table_name',      // e.g. 'ledgers'
        'record_id',       // ID of the record in that table
        'old_values',      // JSON of old values
        'new_values',      // JSON of new values
        'status',          // pending | approved | rejected
        'submitted_by',    // user who submitted
        'approved_by',     // admin who approved/rejected
    ];

    // Optionally cast JSON columns to arrays for easier access
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];
    
    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get the admin who approved or rejected this update.
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
