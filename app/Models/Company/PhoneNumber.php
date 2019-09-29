<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Contracts\CanAcceptIncomingCalls;
use \App\Traits\AcceptsIncomingCalls;
use \App\Traits\HandlesPhoneNumbers;
use \App\Models\User;
use \App\Models\Company\Campaign;
use \App\Models\Company\PhoneNumberPool;
use \App\Models\Company\PhoneNumberConfig;

class PhoneNumber extends Model implements CanAcceptIncomingCalls
{
    use SoftDeletes, AcceptsIncomingCalls, HandlesPhoneNumbers;

    protected $fillable = [
        'uuid',
        'company_id',
        'created_by',
        'external_id',
        'country_code',
        'number',
        'voice',
        'sms',
        'mms',
        'phone_number_pool_id',
        'phone_number_config_id',
        'campaign_id',
        'name',
        'assigned_at'
    ];

    protected $hidden = [
        'company_id',
        'created_by',
        'external_id',
        'last_assigned_at',
        'deleted_at'
    ];

    public function company()
    {
        return $this->belongsTo('\App\Models\Company');
    }

    /**
     * Search for a phone number
     * 
     */
    static public function lookup($areaCode = null, $local = true, $config = [], int $limit = 20, string $country = 'US')
    {
        $client = self::client();

        //  Handle local or toll free portion
        $query = $client->availablePhoneNumbers($country);
        $query = $local ? $query->local : $query->tollFree;

        //  Handle config
        if( $areaCode )
            $config['areaCode'] = $areaCode;

        $numbers = $query->read($config, $limit);

        $results = [];

        foreach($numbers as $number){
            
            $results[] = [
                'local'           => $local,
                'toll_free'       => !$local,
                'phone'           => $number->phoneNumber,
                'phone_formatted' => $number->friendlyName
            ];
        }

        return $results;
    }

    /**
     * Purchase a new phone number
     *
     * @param string $phone
     * 
     * @return array
     */
    static public function purchase(string $phone)
    {
        $client = self::client();

        $rootActionPath = trim(env('APP_API_URL'), '/') . '/incoming';

        $num = $client->incomingPhoneNumbers
                         ->create([
                            'phoneNumber'           => $phone,
                            'voiceUrl'              => route('incoming-call'),
                            'voiceMethod'           => 'GET',
                            'statusCallback'        => route('incoming-call-status-changed'),
                            'statusCallbackMethod'  => 'GET',
                            'smsUrl'                => route('incoming-sms'),
                            'smsMethod'             => 'GET',
                            'mmsUrl'                => route('incoming-mms'),
                            'mmsMethod'             => 'GET'
                        ]);
                        
        return [
            'sid'          => $num->sid,
            'country_code' => self::countryCode($num->phoneNumber),
            'number'       => self::number($num->phoneNumber),
            'capabilities' => $num->capabilities
        ];
    }
    
    /**
     * Release a phone number
     * 
     */
    public function release()
    {
        if( env('APP_ENV') !== 'testing' ){
            self::client()
                ->incomingPhoneNumbers($this->external_id)
                ->delete();
        }

        $this->delete();
       
        return $this;
    }

    /**
     * Clean a phone number
     * 
     */
    static public function cleanPhone($phoneStr)
    {
        return preg_replace('/[^0-9]+/', '', $phoneStr);
    }

    /**
     * Pull phone number segment of a phone number
     * 
     */
    static public function number($phoneStr)
    {
        $phone = self::cleanPhone($phoneStr); 

        $neg = strlen($phone) >=  10 ? -10 : 0 - strlen($phone);

        return substr($phone, $neg);
    }

    /**
     * Pull country code segment of a phone number
     * 
     */
    static public function countryCode($phoneStr)
    {
        $fullPhone = self::cleanPhone($phoneStr);
        $phone     = self::number($phoneStr);

        $len = strlen($fullPhone) - strlen($phone);

        return substr($fullPhone, 0, $len);
    }

    /**
     * Determine if this phone number is in use
     * 
     * @return boolean
     */
    public function isInUse()
    {
        //  If not attached to a pool
        return $this->campaign_id || $this->phone_number_pool_id  ? true : false;
    }

    /**
     * Given a list of number ids, return the phone number ids of records in use
     * This included phone numbers attached to a campaign or a phone number pool
     * 
     * @param array $numberIds    An array of phone number ids
     * 
     * @return array
     */
    static public function numbersInUse(array $numberIds = [], $excludingCampaignId = null)
    {
        if( count($numberIds) ){
            $query = PhoneNumber::whereIn('id', $numberIds)
                                ->where(function($q){
                                    $q->where(function($q){
                                        //  Where not attached to a pool, but attached to a campaign
                                        $q->whereNull('phone_number_pool_id')
                                          ->whereNotNull('campaign_id');
                                    })
                                    ->orWhere(function($q){
                                        //  Where attached to a pool
                                        $q->whereNotNull('phone_number_pool_id');
                                    });
                                });

            if( $excludingCampaignId )
                $query->where('campaign_id', '!=', $excludingCampaignId);

            $phoneNumbersInUse = $query->get();

            if( count($phoneNumbersInUse) )
                return array_column($phoneNumbersInUse->toArray(), 'id');
        }
     
        return [];
    }
    
    /**
     * Get a phone number's config
     * 
     * @return \App\Models\Company\PhoneNumberConfig
     */
    public function getPhoneNumberConfig() : PhoneNumberConfig
    {
        if( $this->phone_number_pool_id ){
            $pool = PhoneNumberPool::find($this->phone_number_pool_id);
            if( $pool )
                return $pool->getPhoneNumberConfig();
        }
        
        return PhoneNumberConfig::find($this->phone_number_config_id);
    }

    /**
     * Get a formatted phone number
     * 
     */
    public function formattedPhoneNumber() : string
    {
        return  ($this->country_code ? '+' . $this->country_code : '') 
                . $this->number;
    }
}
