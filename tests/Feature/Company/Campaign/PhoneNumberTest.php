<?php

namespace Tests\Feature\Company\Campaign;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Company\PhoneNumber;
use App\Models\Company\Campaign;

class PhoneNumberTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test adding phone numbers to a campaign
     *
     * @group feature-campaign-phone-numbers
     */
    public function testAdd()
    {
        $user = $this->createUser();

        $phoneNumber = $this->createPhoneNumber([
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]);

        $phoneNumber2 = $this->createPhoneNumber([
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]);

        $campaign = $this->createCampaign();
        
        $response = $this->json('POST', route('add-campaign-phone-number', [
            'company' => $this->company->id,
            'campaign' => $campaign->id
        ]), [
            'phone_numbers' => [
                $phoneNumber->id, 
                $phoneNumber2->id
            ]
        ], $this->authHeaders());

        $response->assertStatus(201);

        $response->assertJSON([
            'message' => 'created',
        ]);

        $phoneNumbers = PhoneNumber::whereIn('id', [$phoneNumber->id, $phoneNumber2->id])->get();
        foreach( $phoneNumbers as $phone ){
            $this->assertTrue($phone->campaign_id == $campaign->id);
        }
    }

    /**
     * Test adding phone numbers to a web campaign fails
     *
     * @group feature-campaign-phone-numbers
     */
    public function testAddToWebCampaignFails()
    {
        $user = $this->createUser();

        $phoneNumber = $this->createPhoneNumber([
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]);

        $phoneNumber2 = $this->createPhoneNumber([
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]);

        $campaign = $this->createCampaign([
            'type' => Campaign::TYPE_WEB
        ]);
        
        $response = $this->json('POST', route('add-campaign-phone-number', [
            'company' => $this->company->id,
            'campaign' => $campaign->id
        ]), [
            'phone_numbers' => [
                $phoneNumber->id, 
                $phoneNumber2->id
            ]
        ], $this->authHeaders());

        $response->assertStatus(400);
        
        $response->assertJSONStructure([
            'error',
        ]);

        $phoneNumbers = PhoneNumber::whereIn('id', [$phoneNumber->id, $phoneNumber2->id])->get();
        foreach( $phoneNumbers as $phone ){
            $this->assertTrue($phone->campaign_id == null);
        }
    }

    /**
     * Test removing phone numbers from a campaign
     *
     * @group feature-campaign-phone-numbers
     */
    public function testRemove()
    {
        $user = $this->createUser();

        $campaign = $this->createCampaign();

        $phoneNumber = $this->createPhoneNumber([
            'campaign_id' => $campaign->id,
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]);

        $phoneNumber2 = $this->createPhoneNumber([
            'campaign_id' => $campaign->id,
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]);
        
        $response = $this->json('DELETE', route('remove-campaign-phone-number', [
            'company' => $this->company->id,
            'campaign' => $campaign->id
        ]), [
            'phone_numbers' => [
                $phoneNumber->id, 
                $phoneNumber2->id
            ]
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'deleted',
        ]);

        $phoneNumbers = PhoneNumber::whereIn('id', [$phoneNumber->id, $phoneNumber2->id])->get();
        foreach( $phoneNumbers as $phone ){
            $this->assertTrue($phone->campaign_id == null);
        }
    }
}
