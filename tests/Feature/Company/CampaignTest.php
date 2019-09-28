<?php

namespace Tests\Feature;

use Tests\TestCase\Company;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\Company\Campaign;

class CampaignTest extends \Tests\TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test listing
     *
     * @group campaigns
     */
    public function testList()
    {
        $user =  $this->createUser();
        $now  = date('Y-m-d H:i:s');

        $campaign1 = factory(Campaign::class)->create([
            'created_by'    => $user->id,
            'company_id'    => $user->company_id,
            'activated_at'  => $now
        ]);

        $campaign2 = factory(Campaign::class)->create([
            'created_by'    => $user->id,
            'company_id'    => $user->company_id,
            'activated_at'  => $now
        ]);

        $response = $this->json('GET', 'http://localhost/v1/companies/' . $this->company->id . '/campaigns', [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJSON([ 
            'message'       => 'success',
            'campaigns'     => [
                ['id' => $campaign1->id, 'activated_at' => $now],
                ['id' => $campaign2->id, 'activated_at' => $now],
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
        $user =  $this->createUser();
        $now  = date('Y-m-d H:i:s');

        $campaign1 = factory(Campaign::class)->create([
            'created_by' => $user->id,
            'company_id' => $user->company_id,
            'activated_at'  => $now
        ]);

        $campaign2 = factory(Campaign::class)->create([
            'created_by' => $user->id,
            'company_id' => $user->company_id,
            'activated_at'  => $now
        ]);

        $response = $this->json('GET', 'http://localhost/v1/companies/' . $this->company->id . '/campaigns', [
            'search' => $campaign2->name
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJSON([ 
            'message'       => 'success',
            'campaigns'     => [
                [
                    'id' => $campaign2->id,
                    'activated_at' => $now
                ],
            ],
            'result_count'  => 1,
            'limit'         => 25,
            'page'          => 1,
            'total_pages'   => 1
        ]);
    }

    /**
     * Test creating a print campaign
     *
     * @group campaigns
     */
    public function testCreatePrintCampaign()
    {
        $this->createUser();
        $now = date('Y-m-d H:i:s');

        $campaign = factory(Campaign::class)->make([
            'type' => Campaign::TYPE_PRINT
        ]);

        $response = $this->json('POST', 'http://localhost/v1/companies/' . $this->company->id. '/campaigns', [
            'name'          => $campaign->name,
            'type'          => $campaign->type,
            'active'        => 1
        ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJSON([
            'campaign' => [
                'name' => $campaign->name,
                'type' => Campaign::TYPE_PRINT,
            ]
        ]);
    }

    /**
     * Test creating a radio campaign
     *
     * @group campaigns
     */
    public function testCreateRadioCampaign()
    {
        $this->createUser();
        $now = date('Y-m-d H:i:s');

        $campaign = factory(Campaign::class)->make([
            'type' => Campaign::TYPE_RADIO
        ]);

        $response = $this->json('POST', 'http://localhost/v1/companies/' . $this->company->id. '/campaigns', [
            'name'          => $campaign->name,
            'type'          => $campaign->type,
            'active'        => 1
        ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJSON([
            'campaign' => [
                'name' => $campaign->name,
                'type' => Campaign::TYPE_RADIO,
                'activated_at' => $now
            ]
        ]);
    }

    /**
     * Test creating a web campaign
     *
     * @group campaigns
     */
    public function testCreateWebCampaign()
    {
        $pool        = $this->createPhoneNumberPool();
        $phoneNumber = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id
        ]);

        $campaign = factory(Campaign::class)->make([
            'type' => Campaign::TYPE_WEB
        ]);

        $numberSwapRules =  json_encode([
            'number_format' => '(###) - ### ####',
            'conditions'    => [
                [
                    'type'  => 'NUMBER_MATCHES',
                    'value' => '8199928829'
                ],
                [
                    'type'  => 'NUMBER_MATCHES',
                    'value' => '8199928829,9039930039'
                ]
            ]
        ]);

        $response = $this->json('POST', 'http://localhost/v1/companies/' . $this->company->id. '/campaigns', [
            'name'              => $campaign->name,
            'type'              => $campaign->type,
            'active'            => true,
            'number_swap_rules' => $numberSwapRules
        ], $this->authHeaders());
        $response->assertStatus(201);
        $response->assertJSON([
            'campaign' => [
                'name' => $campaign->name,
                'type' => Campaign::TYPE_WEB,
                'activated_at' => date('Y-m-d H:i:s'),
                'number_swap_rules' => $numberSwapRules
            ]
        ]);
    }

    /**
     * Test creating a web campaign with an invalid rule number format
     *
     * @group campaigns
     */
    public function testCreateWebCampaignWithInvalidRuleNumberFormat()
    {
        $this->createUser();
        $now = date('Y-m-d H:i:s');

        $campaign = factory(Campaign::class)->make([
            'type' => Campaign::TYPE_WEB
        ]);

        $numberSwapRules =  json_encode([
            'number_format' => '+# (###) - ### #### ####',
            'conditions'    => [
                [
                    'type'  => 'NUMBER_MATCHES',
                    'value' => '8199928829'
                ],
                [
                    'type'  => 'NUMBER_MATCHES',
                    'value' => '8199928829,9039930039'
                ]
            ]
        ]);

        $response = $this->json('POST', 'http://localhost/v1/companies/' . $this->company->id. '/campaigns', [
            'name'              => $campaign->name,
            'type'              => $campaign->type,
            'active'            => true,
            'number_swap_rules' => $numberSwapRules
        ], $this->authHeaders());
        $response->assertStatus(400);
        $response->assertJSONStructure([
            'error'
        ]);
    }

    /**
     * Test creating a web campaign with an invalid rule condition
     *
     * @group campaigns
     */
    public function testCreateWebCampaignWithInvalidRuleCondition()
    {
        $this->createUser();
        $now = date('Y-m-d H:i:s');

        $campaign = factory(Campaign::class)->make([
            'type' => Campaign::TYPE_WEB
        ]);

        $numberSwapRules =  json_encode([
            'number_format' => '+# (###) - ### ####',
            'conditions'    => [
                [
                    'type'  => 'NUMBER_MATCHES',
                    'value' => '8199928829'
                ],
                [
                    'type'  => 'NUMBER_MATCHES',
                    'value' => ['9039930039']
                ]
            ]
        ]);

        $response = $this->json('POST', 'http://localhost/v1/companies/' . $this->company->id. '/campaigns', [
            'name'              => $campaign->name,
            'type'              => $campaign->type,
            'active'            => true,
            'number_swap_rules' => $numberSwapRules
        ], $this->authHeaders());

        $response->assertStatus(400);
        $response->assertJSONStructure([
            'error'
        ]);
    }

    /**
     * Test creating a web campaign with an invalid rule condition type
     *
     * @group campaigns
     */
    public function testCreateWebCampaignWithInvalidRuleConditionType()
    {
        $this->createUser();
        $now = date('Y-m-d H:i:s');

        $campaign = factory(Campaign::class)->make([
            'type' => Campaign::TYPE_WEB
        ]);

        $numberSwapRules =  json_encode([
            'number_format' => '+# (###) - ### ####',
            'conditions'    => [
                [
                    'type'  => 'NUMBER_MATCH',
                    'value' => '8199928829'
                ],
                [
                    'type'  => 'NUMBER_MATCHES',
                    'value' => '9039930039'
                ]
            ]
        ]);

        $response = $this->json('POST', 'http://localhost/v1/companies/' . $this->company->id. '/campaigns', [
            'name'              => $campaign->name,
            'type'              => $campaign->type,
            'active'            => true,
            'number_swap_rules' => $numberSwapRules
        ], $this->authHeaders());
        $response->assertStatus(400);
        $response->assertJSONStructure([
            'error'
        ]);
    }

    /**
     * Test viewing a campaign
     * 
     * @group campaigns
     */
    public function testRead()
    {
        $user = $this->createUser();
        $now  = date('Y-m-d H:i:s');

        $campaign    = factory(Campaign::class)->create([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'type'         => Campaign::TYPE_WEB,
            'activated_at' => $now,
        ]);

        $response = $this->json('GET', 'http://localhost/v1/companies/' . $this->company->id . '/campaigns/' . $campaign->id, [], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'campaign' => [
                'id'   => $campaign->id,
                'name' => $campaign->name,
                'activated_at' => $now
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
        $user = $this->createUser();
        $now  = date('Y-m-d H:i:s');

        $campaign    = factory(Campaign::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'activated_at' => $now
        ]);

        $newCampaignName = $campaign->name . '_UPDATED_' . mt_rand(999,999999);
    
        $response = $this->json('PUT', 'http://localhost/v1/companies/' . $this->company->id . '/campaigns/' . $campaign->id, [
            'name'   => $newCampaignName,
            'active' => 1,
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'campaign' => [
                'id'     => $campaign->id,
                'name'   => $newCampaignName,
                'activated_at' => $now
            ]
        ]);
    }

    /**
     * Test deleting a campaign
     * 
     * @group campaigns
     */
    public function testDelete()
    {
        $user = $this->createUser();
        
        $campaign    = factory(Campaign::class)->create([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => null
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
    public function testDeleteActiveCampaign()
    {
        $user = $this->createUser();
        $now  = date('Y-m-d H:i:s');
        
        $campaign    = factory(Campaign::class)->create([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => $now
        ]);
    
        $response = $this->json('DELETE', 'http://localhost/v1/companies/' . $this->company->id . '/campaigns/' . $campaign->id, [], $this->authHeaders());

        $response->assertStatus(400);

        $response->assertJSON([
            'error' => 'You cannot delete an active campaign'
        ]);

        $this->assertTrue(Campaign::find($campaign->id) != null);
    }
}
