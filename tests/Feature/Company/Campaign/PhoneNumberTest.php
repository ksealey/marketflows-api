<?php

namespace Tests\Feature\Company\Campaign;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Company\PhoneNumber;
use App\Models\Company\Campaign;
use App\Models\Company\CampaignPhoneNumber;

class PhoneNumberTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test adding phone numbers to a campaign
     *
     * @group campaign-phone-numbers
     */
    public function testAdd()
    {
        $user = $this->createUser();

        $phoneNumber = factory(PhoneNumber::class)->create([
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]);

        $phoneNumber2 = factory(PhoneNumber::class)->create([
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]);

        $campaign = $this->createCampaign();
        
        $response = $this->json('POST','http://localhost/v1/companies/' . $campaign->company_id . '/campaigns/' . $campaign->id . '/phone-numbers', [
            'phone_numbers' => [
                $phoneNumber->id, 
                $phoneNumber2->id
            ]
        ], $this->authHeaders());

        $response->assertStatus(201);

        $response->assertJSON([
            'message' => 'created',
        ]);

        $this->assertTrue(CampaignPhoneNumber::where('campaign_id', $campaign->id)->count() === 2);
    }

    /**
     * Test adding phone numbers to a web campaign fails
     *
     * @group campaign-phone-numbers
     */
    public function testAddToWebCampaignFails()
    {
        $user = $this->createUser();

        $phoneNumber = factory(PhoneNumber::class)->create([
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]);

        $phoneNumber2 = factory(PhoneNumber::class)->create([
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]);

        $campaign = $this->createCampaign([
            'type' => Campaign::TYPE_WEB
        ]);
        
        $response = $this->json('POST','http://localhost/v1/companies/' . $campaign->company_id . '/campaigns/' . $campaign->id . '/phone-numbers', [
            'phone_numbers' => [
                $phoneNumber->id, 
                $phoneNumber2->id
            ]
        ], $this->authHeaders());

        $response->assertStatus(400);
        
        $response->assertJSONStructure([
            'error',
        ]);

        $this->assertTrue(CampaignPhoneNumber::where('campaign_id', $campaign->id)->count() === 0);
    }

    /**
     * Test removing phone numbers from a campaign
     *
     * @group campaign-phone-numbers
     */
    public function testRemove()
    {
        $user = $this->createUser();

        $phoneNumber = factory(PhoneNumber::class)->create([
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]);

        $phoneNumber2 = factory(PhoneNumber::class)->create([
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]);

        $campaign = $this->createCampaign();

        CampaignPhoneNumber::create([
            'campaign_id' => $campaign->id,
            'phone_number_id' => $phoneNumber->id,
        ]);

        CampaignPhoneNumber::create([
            'campaign_id' => $campaign->id,
            'phone_number_id' => $phoneNumber2->id,
        ]);
        
        $response = $this->json('DELETE','http://localhost/v1/companies/' . $campaign->company_id . '/campaigns/' . $campaign->id . '/phone-numbers', [
            'phone_numbers' => [
                $phoneNumber->id, 
                $phoneNumber2->id
            ]
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'deleted',
        ]);

        $this->assertTrue(CampaignPhoneNumber::where('campaign_id', $campaign->id)->count() === 0);
    }
}
