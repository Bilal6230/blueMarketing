<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabourLedger extends Model
{
    use \App\Models\Concerns\ProtectsFinancialHistory;
    use HasFactory;
    protected $fillable = ['project_id', 'labour_id', 'contractor_id', 'amount', 'date', 'details','customer_ledger_id'];
    public function customerLedger()
    {
        return $this->belongsTo(CustomerLedger::class,'customer_ledger_id');
    }
}
