<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'relate',
        'father_name',
        'gender',
        'nic_number',
        'phone_number',
        'mobile_number',
        'area_id',
        'type',
        'business',
        'sectors',
        'designation',
        'zone_id',
        'home_address',
        'office_address',
        'follow_id',
        'project_id',
        'is_customer',
        'follow_status',
        'follow_up',

        'is_active',
        'create_by',

    ];

    public function users()
    {
        //return $this->belongsToMany(User::class);
        return $this->belongsToMany(User::class, 'lead_user');
    }

    public function comments()
    {
        return $this->hasMany(Work::class)->orderBy("id", "desc");
    }


    // Fetch only active bookings by default
    public function bookings()
    {
        return $this->hasMany(Booking::class)->where('cancel_status', '0')->orderBy("id", "desc");
    }

    // Fetch bookings including soft-deleted ones
    public function allBookings()
    {
        return $this->hasMany(Booking::class)->withTrashed()->orderBy("id", "desc");
    }

    // Fetch only soft-deleted bookings
    public function deletedBookings()
    {
        return $this->hasMany(Booking::class)->onlyTrashed()->orderBy("id", "desc");
    }
    public function cancelledBookings()
    {
        return $this->hasMany(Booking::class)->where('cancel_status', '1')->orderBy("id", "desc");
    }


    public function project()
    {
        return $this->belongsTo(Project::class);
    }


}

