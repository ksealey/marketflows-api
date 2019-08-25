<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserInvite extends Model
{
    protected $fillable = [
        'created_by',
        'role_id',
        'as_admin',
        'as_client',
        'companies',
        'expires_at',
        'email',
        'key'
    ];

    protected $hidden = [
        'created_by',
        'role_id',
        'company_id',
        'key'
    ];
}
