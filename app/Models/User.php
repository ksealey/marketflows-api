<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use \App\Models\EmailVerification;
use \App\Models\Company;
use \App\Models\Company\BlockedPhoneNumber;
use \App\Models\Company\BlockedPhoneNumber\BlockedCall;
use \App\Models\Company\Report;
use \App\Models\Company\ReportAutomation;
use \App\Models\Company\AudioClip;
use \App\Models\Company\Call;
use \App\Models\Company\CallRecording;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\PhoneNumberConfig;
use \App\Jobs\BatchDeleteAudioJob;
use \App\Jobs\BatchDeletePhoneNumbersJob;
use \App\Jobs\BatchDeleteCallRecordingsJob;
use \App\Traits\PerformsExport;
use Mail;
use DateTime;
use Cache;
use DB;

class User extends Authenticatable
{
    use SoftDeletes, PerformsExport;

    const ROLE_ADMIN     = 'ADMIN';
    const ROLE_SYSTEM    = 'SYSTEM';
    const ROLE_REPORTING = 'REPORTING';
    const ROLE_CLIENT    = 'CLIENT';

    private $emailVerification;

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
        'email_verified_at',
        'phone_verified_at',
        'last_login_at',
        'login_disabled_until',
        'login_attempts',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $hidden = [
        'password_hash',
        'password_reset_token',
        'password_reset_expires_at',
        'auth_token',
        'last_login_at',
        'login_disabled_until',
        'login_attempts',
        'deleted_at'
    ];
 
    public $appends = [
        'full_name',
        'status'
    ];

    public $casts = [
        'login_disabled' => 'boolean'
    ];
    
    static public function roles()
    {
        return [ 
            self::ROLE_ADMIN,
            self::ROLE_SYSTEM,
            self::ROLE_REPORTING,
            self::ROLE_CLIENT
        ];
    }

    static public function exports() : array
    {
        return [
            'id'                => 'Id',
            'first_name'        => 'First Name',
            'last_name'         => 'Last Name',
            'email'             => 'Email',
            'created_at_local'  => 'Created'
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Users';
    }

    static public function exportQuery($user, array $input)
    {
        return User::select('users.*', DB::raw("DATE_FORMAT(CONVERT_TZ(created_at, 'UTC','" . $user->timezone . "'), '%b %d, %Y') AS created_at_local"))
                    ->where('account_id', $user->account_id);
    }

    /**
     * Relationships
     * 
     */
    public function account()
    {
        return $this->belongsTo('\App\Models\Account');
    }

    public function settings()
    {
        $this->hasOne('\App\Models\UserSettings');
    }

    public function getFullNameAttribute()
    {
        return  ucfirst(strtolower($this->first_name)) . ' ' . ucfirst(strtolower($this->last_name));
    }

    public function getStatusAttribute()
    {
        if( $this->login_disabled_until || $this->login_disabled_until )
            return 'Disabled';

        if( ! $this->last_login_at )
            return 'Inactive';

        return 'Active';
    }

    public function canDoAction($action)
    {
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

    /**
     * Remove a company from the system along with all traces of it
     * 
     */
    public function deleteCompany(Company $company)
    {
        Call::where('company_id', $company->id)->update([
            'deleted_by' => $this->id,
            'deleted_at' => now()
        ]);
        
        PhoneNumberConfig::where('company_id', $company->id)->update([
            'deleted_by' => $this->id,
            'deleted_at' => now()
        ]);

        BlockedCall::whereIn('blocked_phone_number_id', function($q) use($company){
                        $q->select('id')
                            ->from('blocked_phone_numbers')
                            ->where('blocked_phone_numbers.company_id', $company->id);
                    })->delete();

        BlockedPhoneNumber::where('company_id', $company->id)->update([
            'deleted_by' => $this->id,
            'deleted_at' => now()
        ]);

        ReportAutomation::whereIn('report_id', function($q) use($company){
                            $q->select('id')
                                ->from('reports')
                                ->where('reports.company_id', $company->id);
                        })->delete();

        Report::where('company_id', $company->id)->update([
            'deleted_by' => $this->id,
            'deleted_at' => now()
        ]);

        //
        //  Batch delete items with remote resources
        //
        BatchDeletePhoneNumbersJob::dispatch($this, $company);
        BatchDeleteAudioJob::dispatch($this, $company);
        BatchDeleteCallRecordingsJob::dispatch($this, $company);
    }
}
