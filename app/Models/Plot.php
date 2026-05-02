<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plot extends Model
{
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

}
