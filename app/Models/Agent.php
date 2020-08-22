<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    protected $fillable = [
        'role',
        'timezone',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password_hash',
        'password_reset_token',
        'password_reset_expires_at',
        'auth_token',
        'last_login_at',
        'login_disabled',
        'login_attempts'
    ];
}
