<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlotDefinition extends Model
{
    protected $fillable = [
        'project_id', 'kind', 'code', 'name', 'size_value', 'unit_code', 'unit_definition_id',
        'legacy_value', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'size_value' => 'decimal:2',
        'is_active' => 'boolean',
        'legacy_value' => 'integer',
    ];

    public function rateRevisions()
    {
        return $this->hasMany(PlotDefinitionRateRevision::class)->orderByDesc('effective_from');
    }

    public function effectiveRate(?string $date = null): ?string
    {
        $date ??= now()->toDateString();
        $rate = $this->rateRevisions()->where('effective_from', '<=', $date)->first();
        return $rate?->rate_per_unit;
    }
}
