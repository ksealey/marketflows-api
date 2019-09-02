<?php
namespace App\Traits;

use App\Models\Company;
use Twilio\Rest\Client as TwilioClient;

trait HandlesPhoneNumbers
{
    static private $client;

    static private $testing = false;

    static private function client($testing = false)
    {
        if( ! self::$client )
            self::$client = new TwilioClient(env('TWILIO_SID'), env('TWILIO_TOKEN'));
        
        return self::$client;
    }

    static public function testing()
    {
        self::$testing = true;
        
        self::$client = new TwilioClient(env('TWILIO_TESTING_SID'), env('TWILIO_TESTING_TOKEN'));
    }
}