<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Punch extends Model
{
    use HasFactory;
    protected $table = 'punches';

    protected $fillable = [
        'user_id',
        'project_id',
        'punch_in',
        'punch_out',
        'post_by',
        
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }



}
