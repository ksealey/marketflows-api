<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\EmailVerification;
use App\Models\Company;
use Mail;
use DateTime;
use Cache;

class User extends Authenticatable
{
    use SoftDeletes;

    const ROLE_ADMIN     = 'ADMIN';
    const ROLE_SYSTEM    = 'SYSTEM';
    const ROLE_REPORTING = 'REPORTING';
    const ROLE_CLIENT    = 'CLIENT';

    private $emailVerification;

    protected $fillable = [
        'id',
        'account_id',
        'company_id',
        'role',
        'timezone',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password_hash',
        'auth_token',
        'email_verified_at',
        'phone_verified_at',
        'last_login_at',
        'password_reset_at',
        'disabled_until',
        'login_attempts',
        'settings',
        'sms_alerts_enabled',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $hidden = [
        'password_hash',
        'auth_token',
        'last_login_at',
        'disabled_until',
        'login_attempts',
        'deleted_at'
    ];

    public $casts = [
        'settings' => 'array'
    ];
    
    /**
     * Relationships
     * 
     */
    public function account()
    {
        return $this->belongsTo('\App\Models\Account');
    }

    public function companies()
    {
        return $this->belongsToMany('App\Models\Company', 'user_companies');
    }

    /**
     * Determine if a user can take an action
     * 
     */
    public function canDoAction($action)
    {
        return true;
    }

    /**
     * Determine if a user can take action on behalf of a company
     * 
     */
    public function canViewCompany($companyId)
    {
        foreach( $this->companies as $company ){
            if( $company->id === $companyId )
                return true;
        }
        return false;
    }

    /**
     * Determine if a user can take action on behalf of a company
     * 
     */
    public function canDoCompanyAction($companyId, $action)
    {
        return $this->canViewCompany($companyId) && $this->canDoAction($action);
    }

    /**
     * Determine if a user is online
     * 
     */
    public function isOnline()
    {
        return Cache::has('websockets.users.' . $this->id);
    }
}
