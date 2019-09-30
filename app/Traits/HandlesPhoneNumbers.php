<?php
namespace App\Traits;

use App\Models\Company;
use Twilio\Rest\Client as TwilioClient;
use App;

trait HandlesPhoneNumbers
{
    static private $client;

    static private $testing = false;

    static private function client()
    {
        return new TwilioClient(env('TWILIO_SID'), env('TWILIO_TOKEN'));
    }

    static public function testClient()
    {
        return new TwilioClient(env('TWILIO_TESTING_SID'), env('TWILIO_TESTING_TOKEN'));
    }
}