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

    public function __construct()
    {
        $this->client = App::make(Twilio::class);
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
        $inProduction = App::environment('prod', 'production');

        return $this->client
                    ->incomingPhoneNumbers
                    ->create([
                            'phoneNumber'           => $number,
                            'voiceUrl'              => $inProduction ? route('incoming-call') : '',
                            'voiceMethod'           => $inProduction ? 'POST' : '',
                            'statusCallback'        => $inProduction ? route('incoming-call-status-changed') : '',
                            'statusCallbackMethod'  => $inProduction ? 'POST' :'',
                            'smsUrl'                => $inProduction ? route('incoming-sms') : '',
                            'smsMethod'             => $inProduction ? 'POST' : '',
                            'mmsUrl'                => $inProduction ? route('incoming-mms') : '',
                            'mmsMethod'             => $inProduction ? 'POST' : '',
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