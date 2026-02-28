<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockBill extends Model
{
    use HasFactory;

    protected $table = 'stock_bills';

    protected $fillable = [
        'bill_no',
        'bill_date',
        'type',      // purchase | sale
        'party_id',
        'entry_id',
        'total_minor',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'party_id' => 'integer',
        'entry_id' => 'integer',
        'total_minor' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function party()
    {
        return $this->belongsTo(StockParty::class, 'party_id', 'id');
    }

    public function lines()
    {
        return $this->hasMany(StockBillLine::class, 'bill_id', 'id');
    }
}
