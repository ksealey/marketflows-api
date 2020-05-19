<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\Call;
use \App\Models\Company\PhoneNumberConfig;
use \App\Traits\CanSwapNumbers;
use \App\Traits\PerformsExport;
use App\Models\TrackingSession;
use App\Models\TrackingSessionEvent;

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
    public function assignNumber($trackingEntity = null)
    {
        $phoneNumber = $this->nextNumber($trackingEntity);
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
    public function nextNumber($trackingEntity = null)
    {
        $phoneNumber = null;
        
        if( $trackingEntity ){
            //  
            //  Look for sessions from this user and use the same number if possible
            //
            $lastSession   = TrackingSession::where('phone_number_pool_id', $this->id)
                                            ->where('tracking_entity_id', $trackingEntity->id)
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

    /**
     * Get the most likely session for a phonenumber
     * 
     */
    public function getSessionData(string $from, PhoneNumber $toPhone)
    {
        $session    = null;

        //
        //  Get the last call from this person linked to a session
        //
        $callerCountryCode = PhoneNumber::countryCode($from);
        $callerNumber      = PhoneNumber::number($from);
        
        $query = Call::where('company_id', $this->company_id)
                      ->where('caller_number', $callerNumber)
                      ->whereNotNull('tracking_entity_id')
                      ->orderBy('created_at', 'DESC');

        if( $callerCountryCode )
            $query->where('caller_country_code', $callerCountryCode);

        $lastSessionedCall = $query->first();
        /*if( $lastSessionedCall ){
            //  Look for the last session attached to the entity attached to this call
            $trackingEntity      = $lastSessionedCall->tracking_entity;
            $lastTrackingSession = 

            $mostRecentSession = TrackingSession::where('tracking_entity_id', $lastSession->tracking_entity_id)
                                                       ->orderBy('created_at', 'DESC')
                                                       ->first();
            //  Same old session
            if( $mostRecentSession->id === $lastSession->id ){

            }
        }*/ 

        //
        //  First see if anyone with an active session clicked this number within the last 15 seconds
        //
        $lastClickEvent = TrackingSessionEvent::where('created_at', '>=', now()->subSeconds(15))
                                            ->whereIn('tracking_session_id', function($query) use($toPhone){
                                                //
                                                //  Get the ids of active sessions attached to this phone number
                                                //
                                                $query->select('id')
                                                        ->from('tracking_sessions')
                                                        ->where('phone_number_id', $toPhone->id)
                                                        ->whereNull('ended_at');
                                            })
                                            ->where('event_type', TrackingSessionEvent::CLICK_TO_CALL)
                                            ->orderBy('created_at', 'desc')
                                            ->first();

        if( $lastClickEvent )
           return $lastClickEvent->tracking_session;

        //
        //  There we no recent click events
        //  look for the last page view or session(Treated almost the same as page view) with an active session
        //
        $lastPageViewEvent = TrackingSessionEvent::whereIn('tracking_session_id', function($query) use($toPhone){
                                                    //
                                                    //  Get the ids of active sessions attached to this phone number
                                                    //
                                                    $query->select('id')
                                                            ->from('tracking_sessions')
                                                            ->where('phone_number_id', $toPhone->id)
                                                            ->whereNull('ended_at');
                                                })
                                                ->whereIn('event_type', [TrackingSessionEvent::PAGE_VIEW, TrackingSessionEvent::SESSION_START])
                                                ->orderBy('created_at', 'desc')
                                                ->first();

        if( $lastPageViewEvent )
            return $lastPageViewEvent->tracking_session;

        return null;
    }
}
