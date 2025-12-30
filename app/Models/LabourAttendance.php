<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabourAttendance extends Model
{
    use HasFactory;
    protected $fillable = [
        'project_id',
        'labour_id',
        'site_id',
        'date',
        'status',
        'hours',
        'ot_hours',
        'rate',
        'amount',
        'site_name',
        'remarks',
        'is_approved',
        'marked_by',
        'paid_status',
        'voucher_status',
        'ratings'
    ];
    public function labour()
    {
        return $this->belongsTo(Labour::class);
    }
    public function site()
    {
        return $this->belongsTo(Site::class)->where('project_id', getSelectedTown());
    }
}
