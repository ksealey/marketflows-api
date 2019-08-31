<?php

namespace Tests\Feature\Company\Campaign;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Company\PhoneNumberPool;
use App\Models\Company\PhoneNumber;
use App\Models\Company\Campaign;
use App\Models\Company\CampaignPhoneNumberPool;

class PhoneNumberPoolTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test adding phone number pools to a campaign
     *
     * @group campaign-phone-number-pools
     */
    public function testAdd()
    {
        $user = $this->createUser();

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]); 

        $phoneNumber = factory(PhoneNumber::class)->create([
            'company_id'            => $this->company->id,
            'created_by'            => $user->id,
            'phone_number_pool_id'  => $pool->id,
        ]);

        $phoneNumber2 = factory(PhoneNumber::class)->create([
            'company_id'            => $this->company->id,
            'created_by'            => $user->id,
            'phone_number_pool_id'  => $pool->id,
        ]);
        
        $campaign = $this->createCampaign();
        
        $response = $this->json('POST','http://localhost/v1/companies/' . $campaign->company_id . '/campaigns/' . $campaign->id . '/phone-number-pools', [
            'phone_number_pool' => $pool->id
        ], $this->authHeaders());

        $response->assertStatus(201);

        $response->assertJSON([
            'message' => 'created',
        ]);

        $this->assertTrue(CampaignPhoneNumberPool::where('campaign_id', $campaign->id)->count() == 1);
    }

    /** 
     * Test removing phone number pools from a campaign
     *
     * @group campaign-phone-number-pools
     */
    public function testRemove()
    {
        $user = $this->createUser();

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]); 

        $phoneNumber = factory(PhoneNumber::class)->create([
            'company_id'            => $this->company->id,
            'created_by'            => $user->id,
            'phone_number_pool_id'  => $pool->id,
        ]);

        $phoneNumber2 = factory(PhoneNumber::class)->create([
            'company_id'            => $this->company->id,
            'created_by'            => $user->id,
            'phone_number_pool_id'  => $pool->id,
        ]);
        
        $campaign = $this->createCampaign();

        CampaignPhoneNumberPool::create([
            'campaign_id'           => $campaign->id,
            'phone_number_pool_id'  => $pool->id,
        ]);
        
        $response = $this->json('DELETE','http://localhost/v1/companies/' . $campaign->company_id . '/campaigns/' . $campaign->id . '/phone-number-pools', [
            'phone_number_pool' => $pool->id
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'deleted',
        ]);

        $this->assertTrue(CampaignPhoneNumberPool::where('campaign_id', $campaign->id)->count() === 0);
    }
}
