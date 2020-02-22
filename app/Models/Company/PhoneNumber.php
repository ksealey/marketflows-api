<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Models\User;
use \App\Models\Company\Campaign;
use \App\Models\Company\PhoneNumberPool;
use \App\Traits\CanSwapNumbers;
use \App\Models\Company\PhoneNumberConfig;
use Twilio\Rest\Client as TwilioClient;
use App;
use Exception;

class PhoneNumber extends Model 
{
    use SoftDeletes, CanSwapNumbers;

    const ERROR_CODE_INVALID     = 21421;
    const ERROR_CODE_UNAVAILABLE = 21422;
    
    protected $fillable = [
        'uuid',
        'external_id',
        'company_id',
        'user_id',
        'phone_number_pool_id',
        'phone_number_config_id',
        'category',
        'sub_category',
        'toll_free',
        'country_code',
        'number',
        'voice',
        'sms',
        'mms',
        'name',
        'source',
        'medium',
        'content',
        'campaign',
        'swap_rules',
        'assignments',
        'last_assigned_at'
    ];

    protected $hidden = [
        'external_id',
        'deleted_at'
    ];

    protected $appends = [
        'link',
        'kind',
        'formatted_number'
    ];

    protected $casts = [
        'swap_rules' => 'array'
    ];

    /**
     * Relationships
     * 
     */
    public function company()
    {
        return $this->belongsTo('\App\Models\Company');
    }

    public function phone_number_config()
    {
        return $this->belongsTo('\App\Models\Company\PhoneNumberConfig');
    }


    static private function client()
    {
        return new TwilioClient(env('TWILIO_SID'), env('TWILIO_TOKEN'));
    }

    static public function testClient()
    {
        return new TwilioClient(env('TWILIO_TESTING_SID'), env('TWILIO_TESTING_TOKEN'));
    }

    /**
     * List local phone numbers available for a gived area code
     * 
     */
    static public function listAvailable($contains = '' , $limit = 20, $tollFree = false, $country = 'US')
    {
        $contains = $contains ? str_pad($contains, 10, '*', STR_PAD_RIGHT) : '';

        $config   = [
            'contains'     => $contains,
            'voiceEnabled' => true,
            'smsEnabled'   => true,
        ]; 

        $client  = self::client();
        $numbers = [];

        try{
            if( $tollFree ){
                $numbers = $client->availablePhoneNumbers($country)
                          ->tollFree
                          ->read($config, $limit);
            }else{
                $numbers = $client->availablePhoneNumbers($country)
                          ->local
                          ->read($config, $limit);
            }
        }catch(Exception $e){ }

        return $numbers ?: [];
    }

    /**
     * Purchase a new phone number
     *
     * @param string $phone
     * 
     * @return array
     */
    static public function purchase(string $number, $disableTestSwap = false)
    {
        if( App::environment(['prod', 'production']) ){
            $client = self::client();
        }else{
            $client = self::testClient();
            if( ! $disableTestSwap )
                $number = config('services.twilio.magic_numbers.available'); 
        }

        return $client->incomingPhoneNumbers
                      ->create([
                            'phoneNumber'           => $number,
                            'voiceUrl'              => route('incoming-call'),
                            'voiceMethod'           => 'GET',
                            'statusCallback'        => route('incoming-call-status-changed'),
                            'statusCallbackMethod'  => 'GET',
                            'smsUrl'                => route('incoming-sms'),
                            'smsMethod'             => 'GET',
                            'mmsUrl'                => route('incoming-mms'),
                            'mmsMethod'             => 'GET'
                        ]);
        
    }
    
    /**
     * Release a phone number
     * 
     */
    public function release()
    {
        if( App::environment(['prod', 'production']) ){
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
     * Get the link
     * 
     */
    public function getLinkAttribute()
    {
        return route('read-phone-number', [
            'companyId'     => $this->company_id,
            'phoneNumberId' => $this->id
        ]);
    }

    /**
     * Get the kind
     * 
     */
    public function getKindAttribute()
    {
        return 'PhoneNumber';
    }

    /**
     * Get a formatted version of the number
     * 
     */
    public function getFormattedNumberAttribute()
    {
        return  '+' 
                . $this->country_code 
                . ' (' 
                . substr($this->number, 0, 3)
                . ') '
                . substr($this->number, 3, 3)
                . '-'
                . substr($this->number, 6, 4);
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
     * Get a formatted phone number
     * 
     */
    public function e164Format()
    {
        return  ($this->country_code ? '+' . $this->country_code : '') 
                . $this->number;
    }

    /**
     * Allow a number to be put be re-used
     * 
     */
    public function unassign()
    {
        $this->assigned_at = null;

        $this->save();

        return $this;
    }

    public function exposedData()
    {
        return [
            'uuid'          => $this->uuid,
            'country_code'  => $this->country_code,
            'number'        => $this->number
        ];
    }
}
