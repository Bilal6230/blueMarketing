<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HoldPlot extends Model
{
    use \App\Models\Concerns\PreservesDeletedRecords;
    use HasFactory;
        protected $fillable = [
        'plot_id',
        'reason',
        'user_id',
    ];

    public function plot()
    {
        return $this->belongsTo(Plot::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
