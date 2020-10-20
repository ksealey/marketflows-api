<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class EmailVerification extends Model
{
    protected $fillable = [
        'email',
        'code',
        'expires_at',
        'verified_at'
    ];
}
