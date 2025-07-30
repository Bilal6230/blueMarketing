<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Work extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'comment',
        'call_duration',
        'call_status',
        'recording_path',
        'user_id',
        'follow_up',
        'type',

    ];

    public function leads()
    {
        return $this->belongsTo(Lead::class,'lead_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function getTodayWorkReport()
    {
        return self::whereDate('created_at', Carbon::today())
            ->with([
                'leads' => function ($query) {
                    $query->select('id', 'first_name', 'last_name', 'phone_number','follow_up','created_at');
                },
                'user' => function ($query) {
                    $query->select('id', 'name');
                },
            ])
            ->get()
            ->groupBy(['user_id', 'lead_id']); // Group after fetching relationships
    }

    


    
    
}
