<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class PhoneVerification extends Model
{
    protected $fillable = [
        'type',
        'record_id',
        'code',
        'expires_at',
        'method'
    ];
        
}
