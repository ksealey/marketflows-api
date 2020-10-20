<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use \App\Models\Company;
use \App\Models\BlockedPhoneNumber;
use \App\Models\BlockedCall;
use \App\Models\Company\Report;
use \App\Models\Company\AudioClip;
use \App\Models\Company\Call;
use \App\Models\Company\CallRecording;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\PhoneNumberConfig;
use \App\Models\Company\Webhook;
use Mail;
use DateTime;
use Cache;
use DB;

class User extends Authenticatable
{
    use SoftDeletes;

    const ROLE_ADMIN     = 'ADMIN';
    const ROLE_SYSTEM    = 'SYSTEM';

    protected $fillable = [
        'id',
        'account_id',
        'role',
        'timezone',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password_hash',
        'password_reset_token',
        'password_reset_expires_at',
        'auth_token',
        'last_login_at',
        'login_attempts',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $hidden = [
        'password_hash',
        'password_reset_expires_at',
        'auth_token',
        'last_login_at',
        'login_attempts',
        'deleted_at',
        'deleted_by'
    ];
 
    public $appends = [
        'full_name',
        'status',
        'kind',
        'link'
    ];

    public $casts = [
        'login_disabled' => 'boolean'
    ];
    
    static public function roles()
    {
        return [ 
            self::ROLE_ADMIN,
            self::ROLE_SYSTEM
        ];
    }

    static public function exports() : array
    {
        return [
            'id'                => 'Id',
            'first_name'        => 'First Name',
            'last_name'         => 'Last Name',
            'email'             => 'Email',
            'role'              => 'Role',
            'created_at_local'  => 'Created'
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Users';
    }

    static public function exportQuery($user, array $input)
    {
        return User::select([
                        'users.*', 
                        DB::raw("DATE_FORMAT(CONVERT_TZ(created_at, 'UTC','" . $user->timezone . "'), '%b %d, %Y') AS created_at_local")
                    ])
                    ->where('account_id', $user->account_id);
    }

    public function getLinkAttribute()
    {
        return route('read-user', [
            'user' => $this->id
        ]);
    }

    public function getKindAttribute()
    {
        return 'User';
    }

    /**
     * Relationships
     * 
     */
    public function account()
    {
        return $this->belongsTo('\App\Models\Account');
    }

    public function getFullNameAttribute()
    {
        return  ucfirst(strtolower($this->first_name)) . ' ' . ucfirst(strtolower($this->last_name));
    }

    public function getStatusAttribute()
    {
        if( $this->login_disabled_at || $this->login_disabled )
            return 'Disabled';

        if( ! $this->last_login_at )
            return 'Inactive';

        return 'Active';
    }

    public function canDoAction($action)
    {
        if( $this->login_disabled || $this->login_disabled_at )
            return false;

        $action = strtolower($action);
        switch( $action ){
            case 'create':
            case 'update':
            case 'delete':
                return $this->role === self::ROLE_ADMIN || $this->role === self::ROLE_SYSTEM;

            case 'read':
                return true; // Anyone can read
            
            default: 
                return false;
        }
    }

    /**
     * Determine if a user can take action on behalf of a company
     * 
     */
    public function canViewCompany(Company $company)
    {
        if( $this->login_disabled || $this->login_disabled_at )
            return false;
            
        return $company->account_id == $this->account_id;
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
