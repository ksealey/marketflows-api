<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Models\User;
use Twilio\Rest\Client as TwilioClient;

class PhoneNumber extends Model
{
    use SoftDeletes;

    static private $client;

    protected $fillable = [
        'company_id',
        'created_by',
        'twilio_id',
        'country_code',
        'number',
        'voice',
        'sms',
        'mms',
        'phone_number_pool_id',
        'name',
        'source',
        'forward_to_country_code',
        'forward_to_number'
    ];

    protected $hidden = [
        'company_id',
        'created_by',
        'twilio_id',
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

        $rootActionPath = trim(env('API_URL'), '/') . '/react';

        $num = $client->incomingPhoneNumbers
                         ->create([
                            'phoneNumber' => $phone,
                            'voiceUrl'    => env('APP_ENV') == 'testing' ? 'http://demo.twilio.com/docs/voice.xml' : $rootActionPath . '/call',
                            'smsUrl'      => env('APP_ENV') == 'testing' ? '' : $rootActionPath . '/sms',
                            'mmsUrl'      => env('APP_ENV') == 'testing' ? '' : $rootActionPath . '/mms'
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
                ->incomingPhoneNumbers($this->twilio_id)
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
        return false;
    }
}
