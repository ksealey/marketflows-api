<?php
namespace App\Helpers;

use Exception;

class PhoneNumberManager
{
    protected $client;

    public function __construct($client)
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
        try{
            $query = $this->client
                          ->availablePhoneNumbers($country);

            $query = $type === 'Toll-Free' ? $query->tollFree : $query->local;

            $numbers = $query->read($config, $limit);
        }catch(Exception $e){ 

        }

        return $numbers;
    }


    public function purchase(string $number)
    {
        return $this->client
                    ->incomingPhoneNumbers
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
    public function release($externalId)
    {
        $this->client->incomingPhoneNumbers($externalId)
                      ->delete();
       
        return $this;
    }
}