<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\PhoneNumberPool;
use \App\Models\Company\Campaign;
use \App\Models\Company\CampaignDomain;
use \App\Models\Company\CampaignPhoneNumber;
use \App\Models\Company\CampaignPhoneNumberPool;
use \App\Jobs\BuildAndPublishCompanyJs;
use Queue;

class CampaignTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test listing
     *
     * @group campaigns
     */
    public function testList()
    {
        $user     =  $this->createUser();

        $campaign1 = factory(Campaign::class)->create([
            'created_by' => $user->id,
            'company_id' => $user->company_id
        ]);

        $campaign2 = factory(Campaign::class)->create([
            'created_by' => $user->id,
            'company_id' => $user->company_id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/companies/' . $this->company->id . '/campaigns', [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJSON([ 
            'message'       => 'success',
            'campaigns'     => [
                ['id' => $campaign1->id],
                ['id' => $campaign2->id],
            ],
            'result_count'  => 2,
            'limit'         => 25,
            'page'          => 1,
            'total_pages'   => 1
        ]);
    }

    /**
     * Test listing with a filter
     *
     * @group campaigns
     */
    public function testListWithFilter()
    {
        $user     =  $this->createUser();

        $campaign1 = factory(Campaign::class)->create([
            'created_by' => $user->id,
            'company_id' => $user->company_id
        ]);

        $campaign2 = factory(Campaign::class)->create([
            'created_by' => $user->id,
            'company_id' => $user->company_id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/companies/' . $this->company->id . '/campaigns', [
            'search' => $campaign2->name
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJSON([ 
            'message'       => 'success',
            'campaigns'     => [
                ['id' => $campaign2->id],
            ],
            'result_count'  => 1,
            'limit'         => 25,
            'page'          => 1,
            'total_pages'   => 1
        ]);
    }

    /**
     * Test creating a print campaign with a phone number
     *
     * @group campaigns
     */
    public function testCreatePrintCampaignWithPhone()
    {
        $myTZ = 'America/New_York';

        $user = $this->createUser([
            'timezone' => $myTZ
        ]);

        $phoneNumber1 = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);
        $phoneNumber2 = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $campaign = factory(Campaign::class)->make([
            'type' => Campaign::TYPE_PRINT
        ]);

        \Queue::fake();
        \Queue::assertNothingPushed();

        $response = $this->json('POST', 'http://localhost/v1/companies/' . $this->company->id. '/campaigns', [
            'name'          => $campaign->name,
            'type'          => $campaign->type,
            'phone_numbers' => [$phoneNumber1->id, $phoneNumber2->id],
            'active'        => 1
        ], $this->authHeaders());

        $myTZ       = new \DateTimeZone($myTZ);
        $expectedTZ = new \DateTimeZone('UTC'); 

        $response->assertStatus(201);
        $response->assertJSON([
            'campaign' => [
                'name' => $campaign->name,
                'type' => $campaign->type,
            ]
        ]);

        //  Make sure the phone number is linked
        $linkCount = CampaignPhoneNumber::whereIn('phone_number_id', [$phoneNumber1->id, $phoneNumber2->id])
                                        ->count();
        
        $this->assertTrue($linkCount === 2);
    }

    /**
     * Test creating a print campaign with a phone number pool
     *
     * @group campaigns
     */
    public function testCreatePrintCampaignWithPhoneNumberPool()
    {
        $myTZ = 'America/New_York';

        $user = $this->createUser([
            'timezone' => $myTZ
        ]);

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);
        $phoneNumber1 = factory(PhoneNumber::class)->create([
            'phone_number_pool_id' => $pool->id,
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);
        $phoneNumber2 = factory(PhoneNumber::class)->create([
            'phone_number_pool_id' => $pool->id,
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $campaign = factory(Campaign::class)->make([
            'type' => Campaign::TYPE_PRINT
        ]);

        \Queue::fake();
        \Queue::assertNothingPushed();

        $response = $this->json('POST', 'http://localhost/v1/companies/' . $this->company->id. '/campaigns', [
            'name'              => $campaign->name,
            'type'              => $campaign->type,
            'phone_number_pool' => $pool->id,
            'active'            => 1
        ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJSON([
            'campaign' => [
                'name' => $campaign->name,
                'type' => $campaign->type
            ]
        ]);

        //  Make sure no phone numbers are linked
        $linkCount = CampaignPhoneNumber::whereIn('phone_number_id', [$phoneNumber1->id, $phoneNumber2->id])
                                        ->count();
        $this->assertTrue($linkCount === 0);

        $responseData = json_decode($response->getContent());

        $poolLinkCount = CampaignPhoneNumberPool::where('phone_number_pool_id', $pool->id)
                                                ->where('campaign_id', $responseData->campaign->id)
                                                ->count();
        $this->assertTrue($poolLinkCount == 1);
    }

    /**
     * Test creating a web campaign with a phone number will fail
     *
     * @group campaigns
     */
    public function testCreateWebCampaignWithPhoneNumberFails()
    {
        $myTZ = 'America/New_York';

        $user = $this->createUser([
            'timezone' => $myTZ
        ]);

        $phoneNumber = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $campaign = factory(Campaign::class)->make([
            'type' => Campaign::TYPE_WEB
        ]);

        $response = $this->json('POST', 'http://localhost/v1/companies/' . $this->company->id. '/campaigns', [
            'name'          => $campaign->name,
            'type'          => $campaign->type,
            'phone_numbers' => [$phoneNumber->id],
            'active'        => 1
        ], $this->authHeaders());

        $myTZ       = new \DateTimeZone($myTZ);
        $expectedTZ = new \DateTimeZone('UTC'); 

        $response->assertStatus(400);
        $response->assertJSONStructure([
            'error'
        ]);
    }

    /**
     * Test creating a web campaign with a phone number pool
     *
     * @group campaigns-
     */
    public function testCreateWebCampaignWithPhoneNumberPool()
    {
        Queue::fake();

        $myTZ = 'America/New_York';

        $user = $this->createUser([
            'timezone' => $myTZ
        ]);

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $phoneNumber = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'phone_number_pool_id' => $pool->id
        ]);

        $campaign = factory(Campaign::class)->make([
            'type' => Campaign::TYPE_WEB
        ]);

        $domains = ['cnn.com', 'www.google.com', 'gmail.com'];

        $response = $this->json('POST', 'http://localhost/v1/companies/' . $this->company->id. '/campaigns', [
            'name'              => $campaign->name,
            'type'              => $campaign->type,
            'phone_number_pool' => $pool->id,
            'domains'           => $domains,
            'active'            => 1
        ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJSON([
            'campaign' => [
                'created_by' => $user->id,
                'name'       => $campaign->name,
                'type'       => $campaign->type
            ]
        ]);

        $responseData = json_decode($response->getContent());
            
        //  Make sure JS Web job was executed
        Queue::assertPushed(BuildAndPublishCompanyJs::class);

        //  Make sure domains where added
        $campaignDomains = CampaignDomain::where('campaign_id', $responseData->campaign->id)->whereIn('domain', $domains)->get();
        $this->assertTrue(count($campaignDomains) == count($domains));

        $domains = ['cnn.com', 'bwick.com'];
        $response = $this->json('PUT', 'http://localhost/v1/companies/' . $this->company->id. '/campaigns/' . $responseData->campaign->id, [
            'name'              => $campaign->name,
            'type'              => $campaign->type,
            'phone_number_pool' => $pool->id,
            'domains'           => $domains,
            'active'            => 1
        ], $this->authHeaders());

        $response->assertStatus(200);
        $responseData = json_decode($response->getContent());
        $campaignDomains = CampaignDomain::where('campaign_id', $responseData->campaign->id)->whereIn('domain', $domains)->get();
        
        $this->assertTrue(count($campaignDomains) == count($domains));
        
    }

    /**
     * Test creating with a phone number in use
     *
     * @group campaigns
     */
    public function testCreateWithPhoneInUse()
    {
        $user = $this->createUser();

        $phoneNumber1 = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        // This one will be in use..
        $phoneNumber2 = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);
        $otherCampaign    = factory(Campaign::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);
        CampaignPhoneNumber::create([
            'campaign_id'     => $otherCampaign->id,
            'phone_number_id' => $phoneNumber2->id
        ]);

        $campaign = factory(Campaign::class)->make();

        $response = $this->json('POST', 'http://localhost/v1/companies/' . $this->company->id . '/campaigns', [
            'name'          => $campaign->name,
            'type'          => Campaign::TYPE_PRINT,
            'phone_numbers' => [$phoneNumber1->id, $phoneNumber2->id],
            'active'        => 1,
        ], $this->authHeaders());

        $response->assertStatus(400);
        $response->assertJSON([
            'error' => 'Phone numbers in use | ' . $phoneNumber2->id
        ]);
    }

    /**
     * Test creating with a phone pool that is in use
     *
     * @group campaigns
     */
    public function testCreateWithPhonePoolInUse()
    {
        $myTZ = 'America/New_York';

        $user = $this->createUser([
            'timezone' => $myTZ
        ]);

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $otherCampaign    = factory(Campaign::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);
        CampaignPhoneNumberPool::create([
            'campaign_id'          => $otherCampaign->id,
            'phone_number_pool_id' => $pool->id
        ]);

        $campaign    = factory(Campaign::class)->make();

        $response = $this->json('POST', 'http://localhost/v1/companies/' . $this->company->id . '/campaigns', [
            'name'      => $campaign->name,
            'type'      => Campaign::TYPE_PRINT,
            'active'    => 1,
            'phone_number_pool' => $pool->id
        ], $this->authHeaders());

        $response->assertStatus(400);
        $response->assertJSON([
            'error' => 'Phone number pool in use'
        ]);

        //  Make sure the phone number pool is linked
        $linkCount = CampaignPhoneNumberPool::where('phone_number_pool_id', $pool->id)
                                            ->count();
        
        $this->assertTrue($linkCount === 1);
    }

    /**
     * Test failing without phone number fields
     *
     * @group campaigns
     */
    public function testFailsWithMissingPhoneFields()
    {
        $user        = $this->createUser();
        $campaign    = factory(Campaign::class)->make();
        $pool        = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $response = $this->json('POST', 'http://localhost/v1/companies/' . $this->company->id . '/campaigns', [
            'name'      => $campaign->name,
            'type'      => $campaign->type,
        ], $this->authHeaders());

        $response->assertStatus(400);
        $response->assertJSONStructure([
            'error'
        ]);
    }

    /**
     * Test read campaign with pool number
     * 
     * @group campaigns
     */
    public function testReadWithPool()
    {
        $user        = $this->createUser();
        $campaign    = factory(Campaign::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        CampaignPhoneNumberPool::create([
            'campaign_id'          => $campaign->id,
            'phone_number_pool_id' => $pool->id
        ]);
    
        $response = $this->json('GET', 'http://localhost/v1/companies/' . $this->company->id . '/campaigns/' . $campaign->id, [], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'campaign' => [
                'id' => $campaign->id,
            ]
        ]);
    }

    /**
     * Test read campaign with phone numbers
     * 
     * @group campaigns
     */
    public function testReadWithPhoneNumbers()
    {
        $user        = $this->createUser();
        $campaign    = factory(Campaign::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $phoneNumber1 = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);
        $phoneNumber2 = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);
        $phoneNumber3 = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        CampaignPhoneNumber::create([
            'campaign_id'     => $campaign->id,
            'phone_number_id' => $phoneNumber1->id
        ]);
        CampaignPhoneNumber::create([
            'campaign_id'     => $campaign->id,
            'phone_number_id' => $phoneNumber2->id
        ]);
        CampaignPhoneNumber::create([
            'campaign_id'     => $campaign->id,
            'phone_number_id' => $phoneNumber3->id
        ]);
    
        $response = $this->json('GET', 'http://localhost/v1/companies/' . $this->company->id . '/campaigns/' . $campaign->id, [], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'campaign' => [
                'id' => $campaign->id,
                'phone_number_pool' => null,
                'phone_numbers' => [
                    [
                        'id' => $phoneNumber1->id
                    ],
                    [
                        'id' => $phoneNumber2->id
                    ],
                    [
                        'id' => $phoneNumber3->id
                    ]
                ]
            ]
        ]);
    }

    /**
     * Test updating a campaign
     * 
     * @group campaigns
     */
    public function testUpdate()
    {
        $user        = $this->createUser([
            'timezone' => 'America/New_York'
        ]);
        $campaign    = factory(Campaign::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $phoneNumber1 = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $phoneNumber2 = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        CampaignPhoneNumber::create([
            'campaign_id'     => $campaign->id,
            'phone_number_id' => $phoneNumber1->id
        ]);
        CampaignPhoneNumber::create([
            'campaign_id'     => $campaign->id,
            'phone_number_id' => $phoneNumber2->id
        ]);

        $newCampaignData = factory(Campaign::class)->make([
            'name' => $campaign->name . '_UPDATED'
        ]);
    
        $response = $this->json('PUT', 'http://localhost/v1/companies/' . $this->company->id . '/campaigns/' . $campaign->id, [
            'name'      => $newCampaignData->name,
            'phone_numbers' => [$phoneNumber1->id, $phoneNumber2->id],
            'active'        => 1,
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'campaign' => [
                'id' => $campaign->id
            ]
        ]);
    }

    /**
     * Test updating a campaign's phone number
     * 
     * @group campaigns
     */
    public function testUpdatePhoneNumber()
    {
        $user        = $this->createUser([
            'timezone' => 'America/New_York'
        ]);
        $campaign    = factory(Campaign::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'type'       => Campaign::TYPE_PRINT
        ]);

        $phoneNumber1 = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        CampaignPhoneNumber::create([
            'campaign_id'     => $campaign->id,
            'phone_number_id' => $phoneNumber1->id
        ]);

        $newCampaignData = factory(Campaign::class)->make();
        $phoneNumber2    = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);
    
        $response = $this->json('PUT', 'http://localhost/v1/companies/' . $this->company->id . '/campaigns/' . $campaign->id, [
            'name'      => $newCampaignData->name,
            'type'      => $campaign->type,
            'active'    => 1,
            'phone_numbers' => [$phoneNumber2->id]
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'campaign' => [
                'id' => $campaign->id
            ]
        ]);

        $campaignPhones = CampaignPhoneNumber::where('campaign_id', $campaign->id)->get();
        $this->assertTrue(count($campaignPhones) == 1);
        $this->assertTrue($campaignPhones->first()->phone_number_id == $phoneNumber2->id);
    }

    /**
     * Test updating a campaign from using a phone number to a pool
     * 
     * @group campaigns
     */
    public function testUpdateFromPhoneToPool()
    {
        $user        = $this->createUser([
            'timezone' => 'America/New_York'
        ]);
        $campaign    = factory(Campaign::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'type'       => Campaign::TYPE_PRINT
        ]);
        $phoneNumber1 = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);
        CampaignPhoneNumber::create([
            'campaign_id'     => $campaign->id,
            'phone_number_id' => $phoneNumber1->id
        ]);
        
        $newCampaignData = factory(Campaign::class)->make();
        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $this->assertTrue(CampaignPhoneNumber::where('campaign_id', $campaign->id)->count() == 1);
        $this->assertTrue(CampaignPhoneNumberPool::where('campaign_id', $campaign->id)->count() == 0); 
        
        $response = $this->json('PUT', 'http://localhost/v1/companies/' . $this->company->id . '/campaigns/' . $campaign->id, [
            'name'      => $newCampaignData->name,
            'type'      => $campaign->type,
            'phone_number_pool' => $pool->id,
            'active' => 1
        ], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJson([
            'campaign' => [
                'id' => $campaign->id
            ]
        ]);

        //  Make sure the phone number links were deleted ... 
        $this->assertTrue(CampaignPhoneNumber::where('campaign_id', $campaign->id)->count() == 0);
        //  And that the new pool is attached
        $campaignPools = CampaignPhoneNumberPool::where('campaign_id', $campaign->id)->get();
        $this->assertTrue(count($campaignPools) == 1);
        $this->assertTrue($campaignPools->first()->phone_number_pool_id == $pool->id);
    }

    /**
     * Test updating a campaign from using a pool to a phone number
     * 
     * @group campaigns
     */
    public function testUpdateFromPoolToPhone()
    {
        $user        = $this->createUser([
            'timezone' => 'America/New_York'
        ]);
        $campaign    = factory(Campaign::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'type'       => Campaign::TYPE_PRINT
        ]);
        

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);
        CampaignPhoneNumberPool::create([
            'campaign_id'          => $campaign->id,
            'phone_number_pool_id' => $pool->id
        ]);
        
        $newCampaignData = factory(Campaign::class)->make();

        $phoneNumber1    = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $phoneNumber2    = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);
        
        $this->assertTrue(CampaignPhoneNumber::where('campaign_id', $campaign->id)->count() == 0);
        $this->assertTrue(CampaignPhoneNumberPool::where('campaign_id', $campaign->id)->count() == 1);

        $response = $this->json('PUT', 'http://localhost/v1/companies/' . $this->company->id . '/campaigns/' . $campaign->id, [
            'name'      => $newCampaignData->name,
            'type'      => $campaign->type,
            'phone_numbers' => [$phoneNumber1->id, $phoneNumber2->id],
            'active' => 1
        ], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJson([
            'campaign' => [
                'id' => $campaign->id
            ]
        ]);

        //  Make sure the pool link was removed
        $this->assertTrue(CampaignPhoneNumberPool::where('campaign_id', $campaign->id)->count() == 0);
        //  Make sure the phone number links were added
        $campaignPhones = CampaignPhoneNumber::where('campaign_id', $campaign->id)->get();
        $this->assertTrue(count($campaignPhones) == 2);
        $this->assertTrue($campaignPhones->first()->phone_number_id == $phoneNumber1->id);
        $this->assertTrue($campaignPhones->last()->phone_number_id == $phoneNumber2->id);
    }

    /**
     * Test deleting a campaign
     * 
     * @group campaigns
     */
    public function testDelete()
    {
        $user        = $this->createUser();
        $campaign    = factory(Campaign::class)->create([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => null
        ]);

        $phoneNumber1 = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);
    
        CampaignPhoneNumber::create([
            'campaign_id'     => $campaign->id,
            'phone_number_id' => $phoneNumber1->id
        ]);
    
        $response = $this->json('DELETE', 'http://localhost/v1/companies/' . $this->company->id . '/campaigns/' . $campaign->id, [], $this->authHeaders());

        $response->assertStatus(200);

        $this->assertTrue(Campaign::find($campaign->id) == null);
    }


    /**
     * Test deleting a campaign that is active
     * 
     * @group campaigns
     */
    public function testDeleteUndatedCampaign()
    {
        $user        = $this->createUser();
        $campaign    = factory(Campaign::class)->create([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => date('Y-m-d H:i:s', strtotime('now -10 days'))
        ]);

        $phoneNumber1 = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);
    
        CampaignPhoneNumber::create([
            'campaign_id'     => $campaign->id,
            'phone_number_id' => $phoneNumber1->id
        ]);
    
        $response = $this->json('DELETE', 'http://localhost/v1/companies/' . $this->company->id . '/campaigns/' . $campaign->id, [], $this->authHeaders());

        $response->assertStatus(400);

        $response->assertJSON([
            'error' => 'You cannot delete active campaigns'
        ]);

        $this->assertTrue(Campaign::find($campaign->id) != null);
    }

    
    /**
     * Test deleting a campaign that was active but is now ended
     * 
     * @group campaigns
     */
    public function testDeleteEndedCampaign()
    {
        $user        = $this->createUser();
        $campaign    = factory(Campaign::class)->create([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => null,
        ]);

        $phoneNumber1 = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);
    
        CampaignPhoneNumber::create([
            'campaign_id'     => $campaign->id,
            'phone_number_id' => $phoneNumber1->id
        ]);
    
        $response = $this->json('DELETE', 'http://localhost/v1/companies/' . $this->company->id . '/campaigns/' . $campaign->id, [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'deleted'
        ]);

        $this->assertTrue(Campaign::find($campaign->id) == null);
    }
}
