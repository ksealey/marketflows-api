<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\PhoneNumberConfig;
use \App\Traits\CanSwapNumbers;
use \App\Traits\PerformsExport;
use App\Models\TrackingSession;
use Exception;
use DateTime;
use DateTimeZone;
use stdClass;
use DB;

class PhoneNumberPool extends Model
{
    use SoftDeletes, CanSwapNumbers, PerformsExport;

    static public $currentAvailablePhoneList = [];

    protected $fillable = [
        'account_id',
        'company_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'phone_number_config_id',
        'override_campaigns',
        'name',
        'swap_rules',
        'disabled_at'
    ];

    protected $hidden = [
        'deleted_at',
        'deleted_by'
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u'; 

    static public function exports() : array
    {
        return [
            'id'                => 'Id',
            'company_id'        => 'Company Id',
            'name'              => 'Name',
            'country_code'      => 'Country Code',
            'number'            => 'Number',
            'type'              => 'Type',
            'assignments'       => 'Assignments',
            'call_count'        => 'Calls',
            'last_call_at'      => 'Last Call Date',
            'created_at'        => 'Created'
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Keyword Tracking Pool Numbers - ' . $input['phone_number_pool_name'];
    }

    static public function exportQuery($user, array $input)
    {
        return PhoneNumber::select([
                    'phone_numbers.*',
                    DB::raw('(SELECT COUNT(*) FROM calls WHERE phone_number_id = phone_numbers.id) AS call_count'),
                    DB::raw('(SELECT MAX(calls.created_at) FROM calls WHERE phone_number_id = phone_numbers.id) AS last_call_at'),
                ])
                ->leftJoin('calls', 'calls.phone_number_id', 'phone_numbers.id')
                ->where('phone_numbers.phone_number_pool_id', $input['phone_number_pool_id']);
    }

    /**
     * Relationships
     * 
     * 
     */
    public function company()
    {
        return $this->belongsTo('\App\Models\Company');
    }

    public function phone_numbers()
    {
        return $this->hasMany('\App\Models\Company\PhoneNumber');
    }

    public function phone_number_config()
    {
        return $this->belongsTo('\App\Models\Company\PhoneNumberConfig');
    }

    /**
     * Attached attributes
     * 
     */
    public function getLinkAttribute()
    {
        return route('read-phone-number-pool', [
            'company'         => $this->company_id,
            'phoneNumberPool' => $this->id
        ]);
    }

    public function getKindAttribute()
    {
        return 'PhoneNumberPool';
    }

    public function getSwapRulesAttribute($rules)
    {
        return json_decode($rules);
    }

    /**
     * Determine if the phone number pool is in use
     * 
     */
    public function isInUse()
    {
        if( count($this->phone_numbers) )
            return true;
        
        return false;
    }

    /**
     * Get and assign the next phone number in line
     * 
     */
    public function assignNumber($persistedId = null)
    {
        $phoneNumber = $this->nextNumber($persistedId);
        if( ! $phoneNumber )
            return null;

        $now = new DateTime();
        $phoneNumber->last_assigned_at = now()->format('Y-m-d H:i:s.u');
        $phoneNumber->assignments++;
        $phoneNumber->save();

        return $phoneNumber;
    }

    /**
     * Get the next number in line
     * 
     */
    public function nextNumber($persistedId = null)
    {
        $phoneNumber = null;
        
        if( $persistedId ){
            //  
            //  Look for sessions from this user and use the same number if less than 30 days ago
            //
            $thirtyDaysAgo = now()->subDays(30);
            $lastSession   = TrackingSession::where('created_at', '>=', $thirtyDaysAgo)
                                            ->where('phone_number_pool_id', $this->id)
                                            ->where('persisted_id', $persistedId)
                                            ->orderBy('created_at', 'desc')
                                            ->first();
            if( $lastSession )
                $phoneNumber = PhoneNumber::find($lastSession->phone_number_id);
        }

        if( ! $phoneNumber )
            $phoneNumber = PhoneNumber::where('phone_number_pool_id', $this->id)
                                    ->orderBy('last_assigned_at', 'ASC')
                                    ->orderBy('id', 'ASC')
                                    ->first();

        return $phoneNumber;
    }
}
