<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\EmailVerification;
use App\Models\Company;

class User extends Authenticatable
{
    use SoftDeletes;

    private $emailVerification;

    protected $fillable = [
        'id',
        'account_id',
        'company_id',
        'role_id',
        'is_admin',
        'is_client',
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

    public function createEmailVerification()
    {
        return EmailVerification::create([
            'user_id'       => $this->id,  
            'key'           => str_random(40),
            'expires_at'    => date('Y-m-d H:i:s', strtotime('now +24 hours'))
        ]);
    }

    public function account()
    {
        return $this->belongsTo('\App\Models\Account');
    }

    public function company()
    {
        return $this->belongsTo('\App\Models\Company');
    }

    public function companies()
    {
        return $this->belongsToMany('App\Models\Company', 'user_companies');
    }

    public function role()
    {
        return $this->belongsTo('\App\Models\Role');
    }

    public function canDoAction($action)
    {
        list($requestedModule, $requestedAction) = explode('.', $action);

        if( $this->is_admin )
            return true;

        //  Only allow the following for clients
        //  Reporting
        //      - read
        if( $this->is_client ){
            if( $action == 'reporting.read' )
                return true;
            return false;
        }

        //  This user can't do a thing...
        if( ! $myRole = $this->role )
            return false;

        $myPolicy = $myRole->policy;
        if( ! $myPolicy )
            return false;

        $policyRules = json_decode($myPolicy);
        if( ! $policyRules || empty($policyRules->can) )
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

    public function canDoCompanyAction(Company $company, $action)
    {
        foreach($this->companies as $myCompany){
            if( $myCompany->id === $company->id )
                return $this->canDoAction($action);
        }

        return false;
    }
}
