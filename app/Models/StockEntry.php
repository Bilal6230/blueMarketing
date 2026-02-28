<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'stock_entries';

    protected $fillable = [
        'slip_no',
        'entry_date',
        'flow',
        'party_id',
        'vehicle_no',
        'driver_name',
        'reason',
        'billed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'billed_at' => 'datetime',
        'party_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function party()
    {
        return $this->belongsTo(StockParty::class, 'party_id', 'id');
    }

    public function lines()
    {
        return $this->hasMany(StockEntryLine::class, 'entry_id', 'id');
    }
}
