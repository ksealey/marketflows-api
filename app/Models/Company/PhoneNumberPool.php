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
            if( $lastSession ){
                $phoneNumber = PhoneNumber::where('id', $lastSession->phone_number_id)
                                          ->whereNull('disabled_at')
                                          ->first();
            }
        }

        if( ! $phoneNumber )
            $phoneNumber = PhoneNumber::where('phone_number_pool_id', $this->id)
                                    ->whereNull('disabled_at')
                                    ->orderBy('last_assigned_at', 'ASC')
                                    ->orderBy('id', 'ASC')
                                    ->first();

        return $phoneNumber;
    }

    /**
     * Get the most likely session for a phonenumber
     * 
     */
    public function getSessionData(string $callerPhone, PhoneNumber $dialedPhoneNumber, $state = null, $city = null)
    {
        $now = new DateTime();

        //
        //  Get the last call to dialed number
        //
        $callerCountryCode = PhoneNumber::countryCode($callerPhone);
        $callerNumber      = PhoneNumber::number($callerPhone);

        $query = Call::where('caller_number', $callerNumber)
                     ->where('phone_number_id', $dialedPhoneNumber->id)
                     ->orderBy('created_at', 'DESC');

        if( $callerCountryCode )
            $query->where('caller_country_code', $callerCountryCode);

        $lastCallToDialedNumber = $query->first();

        //
        //  If the caller has called this number before, use existing call to find session
        //
        if( $lastCallToDialedNumber ){
            //
            //  If no session was linked the first time, treat it as new
            //
            if( ! $lastCallToDialedNumber->tracking_session_id )
                return $this->getSessionByEvents($dialedPhoneNumber);
            
            //
            //  See if there's any unclaimed session for this user. If not, it's a redial or from another device
            //
            $previousSession = $lastCallToDialedNumber->tracking_session;
            $lastSession     = TrackingSession::where('tracking_entity_id', $previousSession->tracking_entity_id)
                                                ->where('company_id', $this->company_id)
                                                ->orderBy('created_at', 'desc')
                                                ->first();
                                    
            if( ! $lastSession->claimed ){
                //  
                //  Claim all sessions related to this caller
                //
                TrackingSession::where('tracking_entity_id', $lastSession->tracking_entity_id)
                               ->where('company_id', $this->company_id)
                               ->update(['claimed' => 1]);

                //  Log inbound call event
                TrackingSessionEvent::create([
                    'tracking_session_id' => $lastSession->id,
                    'event_type'          => TrackingSessionEvent::INBOUND_CALL,
                    'created_at'          => $now->format('Y-m-d H:i:s.u'),
                    'content'             => $dialedPhoneNumber->e164Format()
                ]);

                return $lastSession;
            }
            
            //
            //  There are no unclaimed sessions. This is a re-dial or the user is on a new device or browser and got the same number
            //

            //
            //  See if there is another active session with the same ip address as the last session
            //  This would mean they're on the same network but with a different device
            //
            $possibleSession = TrackingSession::where('phone_number_id', $dialedPhoneNumber->id)
                                              ->whereNull('ended_at')
                                              ->where('claimed', 0)
                                              ->where('tracking_entity_id', '!=', $lastSession->id)
                                              ->where('ip', $lastSession->ip)
                                              ->orderBy('created_at', 'DESC')
                                              ->first();
            
            if( $possibleSession ){
                $possibleSession->claimed = 1;
                $possibleSession->save();

                $this->createInboundCallEvent($possibleSession, $dialedPhoneNumber);

                return $possibleSession;
            }

            //
            //  If there's an active session for this number that's unclaimed, with recent events
            //  the caller may be on a new device or browser, or we could not fingerprint them properly, so treat it as new
            //
            $activeSessionsWithRecentEventsCount = TrackingSession::where('phone_number_id', $dialedPhoneNumber->id)
                                                                    ->whereNull('ended_at')
                                                                    ->where('claimed', 0)
                                                                    ->whereIn('id', function($query){
                                                                        $query->select('tracking_session_id')
                                                                            ->from('tracking_session_events')
                                                                            ->where('tracking_session_id', 'tracking_sessions.id')
                                                                            ->where(function($query){
                                                                                    $query->where('event_type', TrackingSessionEvent::CLICK_TO_CALL)
                                                                                        ->where('created_at', '>=', now()->subSeconds(15));
                                                                            })
                                                                            ->orWhere(function($query){
                                                                                $query->where('event_type', TrackingSessionEvent::PAGE_VIEW)
                                                                                    ->where('created_at', '>=', now()->subSeconds(60));
                                                                            });
                                                                    })
                                                                    ->count();
            if( $activeSessionsWithRecentEventsCount ){
                //  
                //  Caller may be from a new device, so treat it as a new set
                //
                return $this->getSessionByEvents($dialedPhoneNumber);
            }else{
                //
                //  There are active sessions but none belong to this caller for known device, return last session
                //
                //  Log inbound call event
                $this->createInboundCallEvent($lastSession, $dialedPhoneNumber);

                return $lastSession;
            }

        }

        //
        //  First time callers will get session based on recent events
        //
        return $this->getSessionByEvents($dialedPhoneNumber);
    }

    public function getSessionByEvents(PhoneNumber $dialedPhoneNumber)
    {
        $now = new DateTime();
        
        //
        //  First see if anyone with an active session clicked this number within the last 15 seconds
        //
        $lastClickEvent = TrackingSessionEvent::where('created_at', '>=', now()->subSeconds(15))
                                            ->whereIn('tracking_session_id', function($query) use($dialedPhoneNumber){
                                                //
                                                //  Get the ids of active sessions attached to this phone number
                                                //
                                                $query->select('id')
                                                        ->from('tracking_sessions')
                                                        ->where('phone_number_id', $dialedPhoneNumber->id)
                                                        ->whereNull('ended_at')
                                                        ->where('claimed', 0);
                                            })
                                            ->where('event_type', TrackingSessionEvent::CLICK_TO_CALL)
                                            ->orderBy('created_at', 'desc')
                                            ->first();

        if( $lastClickEvent ){
            $session          = $lastClickEvent->tracking_session;
            $session->claimed = 1;
            $session->save();

            $this->createInboundCallEvent($session, $dialedPhoneNumber);
            
            return $session;
        }
       
        //
        //  There we no recent click events
        //  look for the last page view with an active session
        //
        $lastPageViewEvent = TrackingSessionEvent::whereIn('tracking_session_id', function($query) use($dialedPhoneNumber){
                                                    //
                                                    //  Get the ids of active sessions attached to this phone number
                                                    //
                                                    $query->select('id')
                                                            ->from('tracking_sessions')
                                                            ->where('phone_number_id', $dialedPhoneNumber->id)
                                                            ->whereNull('ended_at')
                                                            ->where('claimed', 0);
                                                })
                                                ->where('event_type', TrackingSessionEvent::PAGE_VIEW)
                                                ->orderBy('created_at', 'desc')
                                                ->first();
        if( $lastPageViewEvent ){
            $session          = $lastPageViewEvent->tracking_session;
            $session->claimed = 1;
            $session->save();

            $this->createInboundCallEvent($session, $dialedPhoneNumber);
            
            return $session;
        }

        return null;
    }

    public function createInboundCallEvent(TrackingSession $session, PhoneNumber $phoneNumber)
    {
        $now = new DateTime();

        return TrackingSessionEvent::create([
            'tracking_session_id' => $session->id,
            'event_type'          => TrackingSessionEvent::INBOUND_CALL,
            'created_at'          => $now->format('Y-m-d H:i:s.u'),
            'content'             => $phoneNumber->e164Format()
        ]);
    }
}
