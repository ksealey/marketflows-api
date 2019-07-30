<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Auth\Authenticable; 
use App\Models\EmailVerification;

class User extends Authenticable
{
    use SoftDeletes;

    private $emailVerification;

    protected $fillable = [
        'id',
        'company_id',
        'timezone',
        'first_name',
        'last_name',
        'email',
        'country_code',
        'area_code',
        'phone',
        'password_hash',
        'email_verified_at',
        'phone_verified_at',
        'last_login_at',
        'password_reset_at',
        'disabled_until',
        'login_attempts',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $hidden = [
        'password_hash',
        'last_login_at',
        'disabled_until',
        'login_attempts',
        'deleted_at'
    ];

    public function createEmailVerification()
    {
        $key   = str_random(40);
        
        $later = date('Y-m-d H:i:s', strtotime('now +24 hours'));

        $this->emailVerification = EmailVerification::create([
            'user_id'       => $this->id,  
            'key'           => $key,
            'expires_at'    => $later
        ]);
    }

    public function getEmailVerification()
    {
        return $this->emailVerification;
    }
}
