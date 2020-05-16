<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Twilio\Rest\Client as Twilio;
use \App\Models\Company\PhoneNumberConfig;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\Call;

class PhoneNumberTest extends TestCase
{
    use \Tests\CreatesAccount, RefreshDatabase;

    /**
     * Test initializing for a campaign
     * 
     * @group online
     */
    public function testInitOnlineCampaign()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $phoneNumber = $this->createPhoneNumber($company, $config);

        $response    = $this->noAuthJson('POST', route('online-init'));
        $response->dump();
    }
    
}
