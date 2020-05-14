<?php
namespace App\Helpers;

use Twilio\Rest\Client as Twilio;
use Exception;
use App;

class PhoneNumberManager
{
    protected $client;

    public function listAvailable($contains = '' , $limit = 20, $type, $country = 'US')
    {
        $config = config('services.twilio');
        $client = new Twilio($config['sid'], $config['token']); 

        $contains = $contains ? str_pad($contains, 10, '*', STR_PAD_RIGHT) : '';

        $config   = [
            'contains'     => $contains,
            'voiceEnabled' => true,
            'smsEnabled'   => true,
        ]; 

        $numbers = [];
        try{
            $query = $client->availablePhoneNumbers($country);

            $query = $type === 'Toll-Free' ? $query->tollFree : $query->local;

            $numbers = $query->read($config, $limit);
        }catch(Exception $e){ 

        }

        return $numbers;
    }


    public function purchase(string $number)
    {
        $config = config('services.twilio');

        if( App::environment(['prod', 'production']) ){
            $client = new Twilio($config['sid'], $config['token']); 
        }else{
            $client = new Twilio($config['test_sid'], $config['test_token']); 
            $number = $config['magic_numbers']['available'];
        }

        return $client->incomingPhoneNumbers
                      ->create([
                            'phoneNumber'           => $number,
                            'voiceUrl'              => route('incoming-call'),
                            'voiceMethod'           => 'POST',
                            'statusCallback'        => route('incoming-call-status-changed'),
                            'statusCallbackMethod'  => 'POST',
                            'smsUrl'                => route('incoming-sms'),
                            'smsMethod'             => 'POST',
                            'mmsUrl'                => route('incoming-mms'),
                            'mmsMethod'             => 'POST'
                      ]);
        
    }

    /**
     * Release a phone number
     * 
     */
    public function release($externalId)
    {

        if( App::environment(['prod', 'production']) ){
            $this->client
                 ->incomingPhoneNumbers($externalId)
                 ->delete();
        }
       
        return $this;
    }
}