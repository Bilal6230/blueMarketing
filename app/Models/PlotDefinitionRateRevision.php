<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlotDefinitionRateRevision extends Model
{
    public $timestamps = false;

    protected $fillable = ['plot_definition_id', 'project_id', 'rate_per_unit', 'effective_from', 'changed_by', 'created_at'];

    protected $casts = ['rate_per_unit' => 'decimal:2', 'effective_from' => 'date'];
}
