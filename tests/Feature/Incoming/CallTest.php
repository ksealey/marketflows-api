<?php

namespace Tests\Feature\Incoming;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumberPool;
use App\Models\Company\Campaign;
use App\Models\Company\CampaignPhoneNumber;
use App\Models\Company\CampaignPhoneNumberPool;
use App\External\TwilioCall;

class CallTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test handling an incoming phone call for a print campaign
     * 
     * @group incoming-calls
     */
    public function testHandleIncomingPhoneCallForPrintCampaign()
    {
        //  Setup
        $number = env('TWILIO_TESTING_NUMBER');

        PhoneNumber::where('number', $number)->delete();

        $phone = $this->createPhoneNumber([
            'number' => $number
        ]);

        $campaign = $this->createCampaign([
            'type' => Campaign::TYPE_PRINT
        ]);

        CampaignPhoneNumber::create([
            'campaign_id'     => $campaign->id,
            'phone_number_id' => $phone->id
        ]);

        $incomingCall = factory(TwilioCall::class)->make();
        
        //  Place "call"
        $response = $this->json('GET', 'http://localhost/v1/incoming/call', [

        ]);

    }
}
