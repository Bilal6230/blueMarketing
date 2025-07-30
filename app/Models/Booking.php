<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Booking extends Model
{
    use HasFactory, SoftDeletes; // Add SoftDeletes trait

    protected $fillable = [
        'project_id',
        'customer_id',
        'plot_id',
        'plot_type',
        'plot_size',
        'plot_rate',
        'is_corner',
        'is_park',
        'dicount_value',
        'park_facing',
        'carner_price',
        'total_price',
        'broker_id',
        'booking_date',
        'profile_image',
        'status',
        'user_id',
    ];

    protected $dates = ['deleted_at']; // Ensure deleted_at is treated as a date
    protected $appends = ['broker_name']; // Automatically add broker_name to the response



    public function bookingDetails()
    {
        return $this->hasMany(BookingDetail::class);
    }

    public function customer()
    {
        return $this->belongsTo(Lead::class, 'customer_id');
    }

    public function plot()
    {
        return $this->belongsTo(Plot::class, 'plot_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function broker()
    {
        return $this->belongsTo(SubheadAccounting::class, 'broker_id');
    }

    public function getBrokerNameAttribute()
    {
        $broker = $this->broker; // Fetch the associated broker (SubheadAccounting model)
        return $broker ? $broker->name : null; // Return the broker's name or null if not found
    }
}
