<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\EmailVerification;
use App\Models\Company;
use Cookie;

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
        'country_code',
        'area_code',
        'phone',
        'password_hash',
        'auth_token',
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
        'auth_token',
        'last_login_at',
        'disabled_until',
        'login_attempts',
        'deleted_at'
    ];

    /**
     * Get the user's account relationship
     * 
     */
    public function account()
    {
        return $this->belongsTo('\App\Models\Account');
    }

    /**
     * Get the user's companies
     * 
     */
    public function companies()
    {
        return $this->belongsToMany('App\Models\Company', 'user_companies');
    }

    /**
     * Get the user's role
     * 
     */
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
    public function canDoCompanyAction(Company $company, $action)
    {
        foreach($this->companies as $myCompany){
            if( $myCompany->id === $company->id )
                return $this->canDoAction($action);
        }

        return false;
    }

    public function profile()
    {
        $myAccount = $this->account;
        $myRole    = $this->role;

        return [
            'account'    => $myAccount,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'email'      => $this->email,
            'role'       => $myRole,
        ];
    }
}
