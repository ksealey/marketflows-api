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

    private $emailVerification;

    protected $fillable = [
        'id',
        'account_id',
        'company_id',
        'role_id',
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
        'email_alerts_enabled',
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

    public function role()
    {
        return $this->belongsTo('\App\Models\Role');
    }

    /**
     * Determine if a user can take an action
     * 
     */
    public function canDoAction($action)
    {
        list($requestedModule, $requestedAction) = explode('.', $action);

        //  This user can't do a thing...
        if( ! $myRole = $this->role )
            return false;

        $myPolicy = $myRole->policy;
        if( ! $myPolicy )
            return false;

        $policyRules = json_decode($myPolicy);
        if( ! $policyRules || empty($policyRules->policy) )
            return false;
        
        foreach( $policyRules->policy as $rule ){
            $myModule = trim(strtolower($rule->module));
            //  Module found
            if( $myModule == '*' || $myModule == $requestedModule ){
                //  Can we do this action?
                $myActions = trim(strtolower($rule->permissions));
                if( $myActions == '*' )
                    return true;
                $myActions = explode(',', $myActions);
                foreach( $myActions as $myAction ){
                    if( $myAction == $requestedAction )
                        return true;
                }
            }
        }

        return false;
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
     * Send mail to this user
     * 
     */
    public function email($mail)
    {
        if( ! $this->email_alerts_enabled_at )
            return;
            
        Mail::to($this->email)
            ->send($mail);
    }

    /**
     * Send sms to this user
     * 
     */
    public function sms($message)
    {
        if( ! $this->phone_verified_at || ! $this->sms_alerts_enabled_at )
            return; 
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
