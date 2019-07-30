<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class EmailVerification extends Model
{
    protected $fillable = [
        'user_id',
        'key',
        'expires_at'
    ];
}
