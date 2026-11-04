<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockPartyProjectAccount extends Model
{
    protected $fillable = [
        'project_id', 'stock_party_id', 'project_head_subhead_id', 'changed_by',
    ];

    protected $casts = [
        'project_id' => 'integer',
        'stock_party_id' => 'integer',
        'project_head_subhead_id' => 'integer',
        'changed_by' => 'integer',
    ];
}
