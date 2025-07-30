<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ledger extends Model
{
    use HasFactory;
    protected $table = 'ledgers';

    protected $fillable = [
        'type',
        'type_id',
        'project_head_subheads_id',
        'reference',
        'amount_in',
        'amount_out',
        'detail',
        'create_by',
        'update_by',
        'is_active',
        'status',
        'date',
    ];

    public function projectHeadSubhead()
    {
        return $this->belongsTo(ProjectHeadSubhead::class, 'project_head_subheads_id');
    }
}
