<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Contracts\CanBeDialed;
use \App\Traits\IsDialed;
use \App\Models\User;
use \App\Models\Company\Campaign;
use \App\Models\Company\CampaignPhoneNumber;
use \App\Models\Company\PhoneNumberPool;
use Twilio\Rest\Client as TwilioClient;

class PhoneNumber extends Model implements CanBeDialed
{
    use SoftDeletes, IsDialed;

    static private $client;

    protected $fillable = [
        'company_id',
        'created_by',
        'external_id',
        'country_code',
        'number',
        'voice',
        'sms',
        'mms',
        'phone_number_pool_id',
        'name',
        'source',
        'forward_to_country_code',
        'forward_to_number',
        'audio_clip_id',
        'recording_enabled_at',
        'whisper_message',
        'whisper_language',
        'whisper_voice',
        'assigned'
    ];

    protected $hidden = [
        'company_id',
        'created_by',
        'external_id',
        'deleted_at'
    ];

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
     */
    static public function purchase(string $phone)
    {
        $client = self::client();

        $rootActionPath = trim(env('APP_API_URL'), '/') . '/incoming';

        $num = $client->incomingPhoneNumbers
                         ->create([
                            'phoneNumber' => $phone,
                            'voiceUrl'    => route('incoming-call'),
                            'smsUrl'      => route('incoming-sms'),
                            'mmsUrl'      => route('incoming-mms')
                        ]);

        return [
            'sid'          => $num->sid,
            'country_code' => self::countryCode($num->phoneNumber),
            'number'       => self::phone($num->phoneNumber),
            'capabilities' => $num->capabilities
        ];
    }

    static private function client($testing = false)
    {
        if( ! self::$client )
            self::$client = new TwilioClient(env('TWILIO_SID'), env('TWILIO_TOKEN'));
        
        return self::$client;
    }

    static public function testing()
    {
        self::$client = new TwilioClient(env('TWILIO_TESTING_SID'), env('TWILIO_TESTING_TOKEN'));
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

    static public function cleanPhone($phoneStr)
    {
        return preg_replace('/[^0-9]+/', '', $phoneStr);
    }

    static public function phone($phoneStr)
    {
        $phone = self::cleanPhone($phoneStr); 

        $neg = strlen($phone) >=  10 ? -10 : 0 - strlen($phone);

        return substr($phone, $neg);
    }

    static public function countryCode($phoneStr)
    {
        $fullPhone = self::cleanPhone($phoneStr);
        $phone     = self::phone($phoneStr);

        $len = strlen($fullPhone) - strlen($phone);

        return substr($fullPhone, 0, $len);
    }

    public function isInUse()
    {
        $linkCount = CampaignPhoneNumber::where('phone_number_id', $this->id)->count();

        if( $linkCount )
            return true;

        if( ! $this->phone_number_pool_id )
            return false;
        
        $pool = PhoneNumberPool::find($this->phone_number_pool_id);
        
        return $pool ? $pool->isInUse() : false;
    }

    static public function numbersInUse(array $numbers = [], $excludingCampaignId = null)
    {
        if( ! count($numbers) )
            return [];

        //  Looks for a direct link
        $query = CampaignPhoneNumber::whereIn('phone_number_id', $numbers);
        if( $excludingCampaignId )
            $query->where('campaign_id', '!=', $excludingCampaignId);
        $numberLinks = $query->get();

        if( count($numberLinks) )
            return array_column($numberLinks->toArray(), 'phone_number_id');
     
        //  Look through a link via pool 
        $numbersInLinkedPools = PhoneNumber::whereIn('phone_number_pool_id', function($query) use($numbers, $excludingCampaignId){
            $query->select('phone_number_pool_id')
                  ->from('campaign_phone_number_pools')
                  ->whereIn('phone_number_pool_id', function($query) use($numbers){
                    $query->select('phone_number_pool_id')
                        ->from('phone_numbers')
                        ->whereIn('id', $numbers);
                });
            if( $excludingCampaignId )
                $query->where('campaign_id', '!=', $excludingCampaignId);
        })->get(); 
        
        if( count($numbersInLinkedPools) )
            return array_column($numbersInLinkedPools->toArray(), 'id');
        
        return [];
    }

    static public function numbersInUseExcludingCampaign(array $numbers = [], $campaignId = null)
    {
        return self::numbersInUse($numbers, $campaignId);
    }
}
