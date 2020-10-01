<?php
namespace App\Services;

use Twilio\Rest\Client as Twilio;
use Exception;
use App;
use App\Models\Company\PhoneNumber;
use \Carbon\Carbon;

class PhoneNumberService
{
    public $client;

    public function listAvailable($contains = '' , $limit = 20, $type, $country = 'US')
    {
        if( ! App::environment(['prod', 'production']) ){
            return array_map(function(){
                $phoneNumber              = new \stdClass();
                $phoneNumber->phoneNumber = config('services.twilio.magic_numbers.available');
                return $phoneNumber;
            }, range(0, $limit));
        }

        $contains = $contains ? str_pad($contains, 10, '*', STR_PAD_RIGHT) : '';
        $client   = App::make(Twilio::class);
        $query    = $client->availablePhoneNumbers($country);
        $query    = $type === 'Toll-Free' ? $query->tollFree : $query->local;

        return $query->read([
            'contains'     => $contains,
            'voiceEnabled' => true,
            'smsEnabled'   => true,
        ], $limit) ?: [];
    }


    public function purchase(string $number)
    {
        if( App::environment('prod', 'production') ){
            $client             = App::make(Twilio::class);
            $method             = 'POST';
            $voiceUrl           = route('incoming-call');
            $smsUrl             = route('incoming-sms');
            $statusCallback     = route('incoming-call-status-changed');
        }else{
            $client             = App::make('TestTwilio');
            $method             = '';
            $voiceUrl           = '';
            $smsUrl             = '';
            $statusCallback     = '';
        }

        return $client->incomingPhoneNumbers
                      ->create([
                            'phoneNumber'           => $number,
                            'voiceUrl'              => $voiceUrl,
                            'voiceMethod'           => $method,
                            'smsUrl'                => $smsUrl,
                            'smsMethod'             => $method,
                            'statusCallback'        => $statusCallback,
                            'statusCallbackMethod'  => $method,
                            'voiceCallerIdLookup'   => true
                       ]);
    }

    /**
     * Release a phone number
     * 
     */
    public function releaseNumber($phoneNumber)
    {
        if( App::environment(['local', 'dev', 'development', 'staging']) )
            return $this;

        $this->client
             ->incomingPhoneNumbers($phoneNumber->external_id)
             ->delete();
       
        return $this;
    }
}