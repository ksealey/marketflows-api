<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\PhoneNumber;
use \App\Models\PhoneNumberPool;
use \App\Models\Campaign;
use \App\Models\CampaignPhoneNumber;
use \App\Models\CampaignPhoneNumberPool;

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

        $response = $this->json('GET', '/v1/campaigns', [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJSON([
            'campaigns' => [
                [
                    'id' => $campaign1->id
                ],
                [
                    'id' => $campaign2->id
                ]
            ],
            'total_count' => 2
        ]);
    }

    /**
     * Test creating
     *
     * @group campaigns
     */
    public function testCreate()
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

        $campaign = factory(Campaign::class)->make();
        $response = $this->json('POST', '/v1/campaigns', [
            'name'          => $campaign->name,
            'type'          => Campaign::TYPE_WEB,
            'starts_at'     => $campaign->starts_at,
            'ends_at'       => $campaign->ends_at,
            'phone_numbers' => [$phoneNumber1->id, $phoneNumber2->id]
        ], $this->authHeaders());

        $myTZ       = new \DateTimeZone($myTZ);
        $expectedTZ = new \DateTimeZone('UTC'); 

        $utcStartsAt = new \DateTime($campaign->starts_at, $myTZ);
        $utcEndsAt   = new \DateTime($campaign->ends_at, $myTZ);

        $utcStartsAt->setTimezone($expectedTZ);
        $utcEndsAt->setTimezone($expectedTZ);

        $response->assertStatus(201);
        $response->assertJSON([
            'campaign' => [
                'name' => $campaign->name,
                'type' => $campaign->type,
                'starts_at' => $utcStartsAt->format('Y-m-d H:i:s'),
                'ends_at'   => $utcEndsAt->format('Y-m-d H:i:s'),
                'phone_numbers' => [
                    [
                        'id' => $phoneNumber1->id
                    ],
                    [
                        'id' => $phoneNumber2->id
                    ]
                ]
            ]
        ]);

        //  Make sure the phone number is linked
        $linkCount = CampaignPhoneNumber::whereIn('phone_number_id', [$phoneNumber1->id, $phoneNumber2->id])
                                        ->count();
        
        $this->assertTrue($linkCount === 2);
    }

    /**
     * Test creating with a phone pool
     *
     * @group campaigns
     */
    public function testCreateWithPhonePool()
    {
        $myTZ = 'America/New_York';

        $user = $this->createUser([
            'timezone' => $myTZ
        ]);

        $campaign    = factory(Campaign::class)->make();
        $pool        = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $response = $this->json('POST', '/v1/campaigns', [
            'name'      => $campaign->name,
            'type'      => Campaign::TYPE_WEB,
            'starts_at' => $campaign->starts_at,
            'ends_at'   => $campaign->ends_at,
            'phone_number_pool' => $pool->id
        ], $this->authHeaders());

        $myTZ       = new \DateTimeZone($myTZ);
        $expectedTZ = new \DateTimeZone('UTC'); 

        $utcStartsAt = new \DateTime($campaign->starts_at, $myTZ);
        $utcEndsAt   = new \DateTime($campaign->ends_at, $myTZ);

        $utcStartsAt->setTimezone($expectedTZ);
        $utcEndsAt->setTimezone($expectedTZ);

        $response->assertStatus(201);
        $response->assertJSON([
            'campaign' => [
                'name'                 => $campaign->name,
                'type'                 => $campaign->type,
                'starts_at'            => $utcStartsAt->format('Y-m-d H:i:s'),
                'ends_at'              => $utcEndsAt->format('Y-m-d H:i:s'),
                'phone_number_pool'    => [
                    'id' => $pool->id
                ]
            ]
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

        $response = $this->json('POST', '/v1/campaigns', [
            'name'      => $campaign->name,
            'type'      => $campaign->type,
            'starts_at' => $campaign->starts_at,
            'ends_at'   => $campaign->ends_at
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
    
        $response = $this->json('GET', '/v1/campaigns/' . $campaign->id, [], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'campaign' => [
                'id' => $campaign->id,
                'phone_number_pool' => [
                    'id' => $pool->id
                ],
                'phone_numbers' => []
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
    
        $response = $this->json('GET', '/v1/campaigns/' . $campaign->id, [], $this->authHeaders());

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

        $newCampaignData = factory(Campaign::class)->make();
    
        $response = $this->json('PUT', '/v1/campaigns/' . $campaign->id, [
            'name'      => $newCampaignData->name,
            'type'      => $newCampaignData->type,
            'starts_at' => $newCampaignData->starts_at,
            'ends_at'   => $newCampaignData->ends_at,
            'phone_numbers' => [$phoneNumber1->id, $phoneNumber2->id]
        ], $this->authHeaders());

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
                    ]
                ]
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
            'created_by' => $user->id
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
    
        $response = $this->json('PUT', '/v1/campaigns/' . $campaign->id, [
            'name'      => $newCampaignData->name,
            'type'      => $newCampaignData->type,
            'starts_at' => $newCampaignData->starts_at,
            'ends_at'   => $newCampaignData->ends_at,
            'phone_numbers' => [$phoneNumber2->id]
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'campaign' => [
                'id' => $campaign->id,
                'phone_number_pool' => null,
                'phone_numbers' => [
                    [
                        'id' => $phoneNumber2->id
                    ]
                ]
            ]
        ]);
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
            'created_by' => $user->id
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
        
        $response = $this->json('PUT', '/v1/campaigns/' . $campaign->id, [
            'name'      => $newCampaignData->name,
            'type'      => $newCampaignData->type,
            'starts_at' => $newCampaignData->starts_at,
            'ends_at'   => $newCampaignData->ends_at,
            'phone_number_pool' => $pool->id
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'campaign' => [
                'id' => $campaign->id,
                'phone_number_pool' => [
                    'id' => $pool->id
                ],
                'phone_numbers' => []
            ]
        ]);

        //  Make sure the phone number links were deleted ... 
        $this->assertTrue(CampaignPhoneNumber::where('campaign_id', $campaign->id)->count() == 0);
        //  And that the new pool is attached
        $this->assertTrue(CampaignPhoneNumberPool::where('campaign_id', $campaign->id)->count() == 1);
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

        $response = $this->json('PUT', '/v1/campaigns/' . $campaign->id, [
            'name'      => $newCampaignData->name,
            'type'      => $newCampaignData->type,
            'starts_at' => $newCampaignData->starts_at,
            'ends_at'   => $newCampaignData->ends_at,
            'phone_numbers' => [$phoneNumber1->id, $phoneNumber2->id]
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'campaign' => [
                'id' => $campaign->id,
                'phone_number_pool' => null,
                'phone_numbers' => [
                    ['id' => $phoneNumber1->id],
                    ['id' => $phoneNumber2->id]
                ]
            ]
        ]);

        //  Make sure the phone number links were deleted ... 
        $this->assertTrue(CampaignPhoneNumber::where('campaign_id', $campaign->id)->count() == 2);
        //  And that the new pool is attached
        $this->assertTrue(CampaignPhoneNumberPool::where('campaign_id', $campaign->id)->count() == 0);
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
    
        $response = $this->json('DELETE', '/v1/campaigns/' . $campaign->id, [], $this->authHeaders());

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
    
        $response = $this->json('DELETE', '/v1/campaigns/' . $campaign->id, [], $this->authHeaders());

        $response->assertStatus(400);

        $response->assertJSON([
            'error' => 'You cannot delete active campaigns'
        ]);

        $this->assertTrue(Campaign::find($campaign->id) != null);
    }

    /**
     * Test deleting a campaign that is active with an end date
     * 
     * @group campaigns
     */
    public function testDeleteDatedCampaign()
    {
        $user        = $this->createUser();
        $campaign    = factory(Campaign::class)->create([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => date('Y-m-d H:i:s', strtotime('now -10 days')),
            'ends_at'      => date('Y-m-d H:i:s', strtotime('now +10 minutes')),
        ]);

        $phoneNumber1 = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);
    
        CampaignPhoneNumber::create([
            'campaign_id'     => $campaign->id,
            'phone_number_id' => $phoneNumber1->id
        ]);
    
        $response = $this->json('DELETE', '/v1/campaigns/' . $campaign->id, [], $this->authHeaders());

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
            'activated_at' => date('Y-m-d H:i:s', strtotime('now -10 days')),
            'ends_at'      => date('Y-m-d H:i:s', strtotime('now -10 minutes')),
        ]);

        $phoneNumber1 = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);
    
        CampaignPhoneNumber::create([
            'campaign_id'     => $campaign->id,
            'phone_number_id' => $phoneNumber1->id
        ]);
    
        $response = $this->json('DELETE', '/v1/campaigns/' . $campaign->id, [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'deleted'
        ]);

        $this->assertTrue(Campaign::find($campaign->id) == null);
    }
}
