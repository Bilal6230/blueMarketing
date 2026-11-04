<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plot extends Model
{
    use \App\Models\Concerns\PreservesDeletedRecords;
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'size',
        'unit',
        'amount',
        'description',
        'is_corner',
        'project_id',
        'road_id',
        'facing_id',
        'is_active',
        'plot_category_definition_id',
        'plot_type_definition_id',
        'plot_size_definition_id',
        'plot_unit_definition_id',
        'sold',
        'plot_history',
        'create_by',

    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function holdPlots()
    {
        return $this->hasOne(HoldPlot::class);
    }
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'plot_id', 'id');
    }
    public function projectHeadSubheads()
    {
        return $this->hasMany(ProjectHeadSubhead::class, 'plot_id', 'id');
    }

    public function categoryDefinition()
    {
        return $this->belongsTo(PlotDefinition::class, 'plot_category_definition_id');
    }

    public function typeDefinition()
    {
        return $this->belongsTo(PlotDefinition::class, 'plot_type_definition_id');
    }

    public function sizeDefinition()
    {
        return $this->belongsTo(PlotDefinition::class, 'plot_size_definition_id');
    }

    public function unitDefinition()
    {
        return $this->belongsTo(PlotDefinition::class, 'plot_unit_definition_id');
    }

}
