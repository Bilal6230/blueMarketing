<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_head_subheads_id',
        'price',
        'detail',
        'cash_in',
        'create_by',
        'is_active',
    ];
    public function projectHeadSubhead()
    {
        return $this->belongsTo(ProjectHeadSubhead::class, 'project_head_subheads_id');
    }
}
