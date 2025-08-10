<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectHeadSubhead extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'head_accounting_id',
        'subhead_accounting_id',
        'plot_id',
        'customer_id',
    ];
    public function headAccounting()
    {
        return $this->belongsTo(HeadAccounting::class, 'head_accounting_id');
    }

    public function subheadAccounting()
    {
        return $this->belongsTo(SubheadAccounting::class, 'subhead_accounting_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
    public function ledgers()
    {
        return $this->hasMany(Ledger::class, 'project_head_subheads_id', 'id');
    }
}
