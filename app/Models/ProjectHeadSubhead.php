<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectHeadSubhead extends Model
{
    use \App\Models\Concerns\PreservesDeletedRecords;
    use HasFactory;

    protected $fillable = [
        'project_id',
        'head_accounting_id',
        'subhead_accounting_id',
        'plot_id',
        'customer_id',
    ];

    /**
     * Restrict account mappings to definitions that may receive new postings.
     * Historical mappings remain queryable through the normal model query.
     */
    public function scopeAvailableForPosting($query)
    {
        return $query
            ->whereHas('headAccounting', fn ($q) => $q->where('is_active', 1))
            ->whereHas('subheadAccounting', fn ($q) => $q->where('is_active', 1));
    }

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

    /** The optional project bank profile attached to this ledger account mapping. */
    public function projectBankAccount()
    {
        // account_mapping_id is globally unique and its composite FK also
        // enforces that the linked bank profile belongs to this mapping's project.
        return $this->hasOne(ProjectBankAccount::class, 'account_mapping_id', 'id');
    }

    public function plot()
    {
        return $this->belongsTo(Plot::class, 'plot_id')->withTrashed();
    }
    public function ledgers()
    {
        return $this->hasMany(Ledger::class, 'project_head_subheads_id', 'id');
    }
}
