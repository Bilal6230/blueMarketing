<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];
}
