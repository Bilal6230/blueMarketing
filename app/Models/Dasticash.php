<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dasticash extends Model
{
    use HasFactory;

    protected $table = 'dasticash';

    protected $fillable = [
        'name',
        'amount',
        'description',
    ];
}
