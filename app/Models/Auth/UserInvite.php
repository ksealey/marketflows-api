<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class UserInvite extends Model
{
    protected $fillable = [
        'invited_by',
        'expires_at',
        'email',
        'key'
    ];
}
