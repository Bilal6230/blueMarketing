<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerLedger extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'customer_ledger';

    protected $fillable = [
        'id',
        'customer_id',
        'project_id',
        'plot_id',
        'type_id',
        'reference',
        'payment_type',
        't_number',
        'bank_id',
        'passing_date',
        'passing_status',
        'check_history',
        'date',
        'transaction_type',
        'amount_in',
        'amount_out',
        'description',
        'is_active',
        'is_approve',
        'note',
        'bank_post_at',
        'delete_reason',
    ];

    protected $casts = [
        'check_history' => 'array', // Automatically cast JSON to array
        'deleted_at' => 'datetime',
    ];

    public function addCheckHistory(array $checkDetails)
    {
        // Get the existing history or initialize an empty array
        $currentHistory = $this->check_history ?? [];

        // Add the new check details to the history
        $currentHistory[] = [
            'id' => $checkDetails['id'] ?? null,
            'check_number' => $checkDetails['check_number'] ?? null,
            'passing_date' => $checkDetails['passing_date'] ?? null,
            'passing_status' => $checkDetails['passing_status'] ?? null,
            'description_note' => $checkDetails['description_note'] ?? null,
            'bank_name' => $checkDetails['bank_name'] ?? null, // Add bank name
            'credit_account_id' => $checkDetails['credit_account_id'] ?? null, // Add credit account ID
            'cleared_by_voucher' => $checkDetails['cleared_by_voucher'] ?? null,
            'updated_by' => $checkDetails['updated_by'] ?? null,
            'updated_at' => now(), // Record the update timestamp
        ];

        // Save the updated history back to the database
        $this->check_history = $currentHistory;
        $this->save();
    }


    // Relationships
    public function customer_list()
    {
        return $this->belongsTo(Lead::class, 'customer_id');
    }

    public function plot_list()
    {
        return $this->belongsTo(Plot::class, 'plot_id');
    }

    public function project_list()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    // Scopes
    public function scopeFilterByCustomerId($query, $customerId)
    {
        if ($customerId) {
            return $query->where('customer_id', $customerId);
        }
    }

    public function scopeFilterByPlotId($query, $plotId)
    {
        if ($plotId) {
            return $query->where('plot_id', $plotId);
        }
    }

    public function scopeFilterByPassingStatus($query, $passingStatus)
    {
        if ($passingStatus !== null) {
            return $query->where('passing_status', $passingStatus);
        }
    }

    public function scopeFilterByBankId($query, $bankId)
    {
        if ($bankId) {
            return $query->where('bank_id', $bankId);
        }
    }

    public function scopeFilterByDateRange($query, $fdate, $tdate)
    {
        if ($fdate && $tdate) {
            return $query->whereDate('passing_date', '>=', $fdate)
                        ->whereDate('passing_date', '<=', $tdate);
        }
    }
    public function ledger()
    {
        return $this->hasOne(Ledger::class, 'customer_ledger_id', 'id');
    }

    public function bookingVoucher()
    {
        return $this->hasOne(BookingVoucher::class, 'customer_ledger_id', 'id');
    }

}
