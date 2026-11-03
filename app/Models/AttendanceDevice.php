<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceDevice extends Model
{
    protected $fillable = ['project_id', 'name', 'vendor', 'model', 'host', 'port', 'connection_type', 'protocol', 'username', 'password', 'enabled'];

    protected $casts = [
        'password' => 'encrypted',
        'enabled' => 'boolean',
    ];

    public function mappings()
    {
        return $this->hasMany(AttendanceDeviceUserMapping::class);
    }
}
