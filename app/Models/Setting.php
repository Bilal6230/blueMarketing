<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use \App\Models\Concerns\PreservesDeletedRecords;
    use HasFactory;
    protected $fillable = [
        'key',
        'value',
        'name',
        'type',
        'ext',
        'category',
    ];
}
