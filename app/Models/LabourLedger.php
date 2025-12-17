<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabourLedger extends Model
{
    use HasFactory;
    protected $fillable = ['labour_id', 'amount', 'date', 'details'];
}
