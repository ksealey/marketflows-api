<?php
namespace App\Helpers;

use Twilio\Rest\Client as Twilio;
use Exception;
use App;
use App\Models\Company\PhoneNumber;
use \Carbon\Carbon;

class PhoneNumberManager
{
    public $client;

    public function __construct(Twilio $client)
    {
        $this->client = $client;
    }

    public function listAvailable($contains = '' , $limit = 20, $type, $country = 'US')
    {
        $contains = $contains ? str_pad($contains, 10, '*', STR_PAD_RIGHT) : '';

        $config   = [
            'contains'     => $contains,
            'voiceEnabled' => true,
            'smsEnabled'   => true,
        ]; 
       
        $numbers = [];
        if( App::environment(['local', 'dev', 'development', 'staging']) ){
            return array_map(function(){
                $phoneNumber = new \stdClass();
                $phoneNumber->phoneNumber = config('services.twilio.magic_numbers.available');
                return $phoneNumber;
            }, [1,2,3,4,5,6,7,8,9,10]);
        }

        $query   = $this->client->availablePhoneNumbers($country);
        $query   = $type === 'Toll-Free' ? $query->tollFree : $query->local;
        $numbers = $query->read($config, $limit);

        return $numbers ?: [];
    }


    public function purchase(string $number)
    {
        return $this->client->incomingPhoneNumbers
                      ->create([
                            'phoneNumber'           => $number,
                            'voiceUrl'              => route('incoming-call'),
                            'voiceMethod'           => 'POST',
                            'statusCallback'        => route('incoming-call-status-changed'),
                            'statusCallbackMethod'  => 'POST',
                            //'smsUrl'                => route('incoming-sms'),
                           // 'smsMethod'             => 'POST',
                            //'mmsUrl'                => route('incoming-mms'),
                           // 'mmsMethod'             => 'POST',
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