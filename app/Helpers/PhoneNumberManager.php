<?php
namespace App\Helpers;

use Twilio\Rest\Client as Twilio;
use Exception;
use App;
use App\Models\BankedPhoneNumber;
use App\Models\Company\PhoneNumber;


class PhoneNumberManager
{
    protected $client;

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
        try{
            $query = $this->client->availablePhoneNumbers($country);

            $query = $type === 'Toll-Free' ? $query->tollFree : $query->local;

            $numbers = $query->read($config, $limit);
        }catch(Exception $e){ 

        }

        return $numbers;
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
                            'smsUrl'                => route('incoming-sms'),
                            'smsMethod'             => 'POST',
                            'mmsUrl'                => route('incoming-mms'),
                            'mmsMethod'             => 'POST',
                            'voiceCallerIdLookup'   => true
                      ]);
        
    }

    /**
     * Release a phone number
     * 
     */
    public function releaseNumber(PhoneNumber $phoneNumber)
    {
        $this->client
             ->incomingPhoneNumbers($phoneNumber->external_id)
             ->delete();
       
        return $this;
    }

    /**
     * Bank a phone number
     * 
     */
    public function bankNumber(PhoneNumber $phoneNumber, $availableNow = false)
    {
        //  Wipe voice and sms url so we won't be charged for calls
        $this->client
             ->incomingPhoneNumbers($phoneNumber->external_id);

        //  Make sure it's released 2 days before it will be renewed
        $purchaseDate  = new Carbon($this->purchased_at);
        $renewDate     = new Carbon($today->format('Y-m-' . $purchaseDate->format('d')));
        
        $releaseBy = $phoneNumber->renewalDate()
                                 ->subDays(2);

        return BankedPhoneNumber::create([
            'released_by_account_id' => $phoneNumber->account_id,
            'external_id'            => $phoneNumber->external_id,
            'country'                => $phoneNumber->country,
            'country_code'           => $phoneNumber->country_code,
            'number'                 => $phoneNumber->number,
            'voice'                  => $phoneNumber->voice,
            'sms'                    => $phoneNumber->sms,
            'mms'                    => $phoneNumber->mms,
            'type'                   => $phoneNumber->type,
            'calls'                  => 0,
            'status'                 => $availableNow ? 'Available' : 'Banked',
            'purchased_at'           => $phoneNumber->purchased_at,
            'release_by'             => $releaseBy
        ]);
    }
}