<?php

namespace Tests\Feature\Company\Campaign;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\Company\Campaign;
use \App\Models\Company\CampaignTarget;

class TargetTest extends TestCase
{
    use \Tests\CreatesUser;

   /**
    * Test creating a campaign target 
    *
    * @group feature-campaign-targets
    */
    public function testCreate()
    {
        $campaign = $this->createCampaign([
            'type' => Campaign::TYPE_WEB
        ]);

        $target = factory(CampaignTarget::class)->make();

        $response = $this->json('POST', route('create-campaign-target', [
            'company' => $this->company->id,
            'campaign' => $campaign->id
        ]), [
            'rules' => $target->rules
        ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJSON([
            'campaign_target' => [
                'campaign_id' => $campaign->id
                
            ],
            
        ]);
    }

   /**
    * Test creating a campaign target on a non-web campaign
    *
    * @group feature-campaign-targets
    */
    public function testCreateFailsWhenNotWebCampaign()
    {
        $campaign = $this->createCampaign([
            'type' => Campaign::TYPE_PRINT
        ]);

        $target = factory(CampaignTarget::class)->make();

        $response = $this->json('POST', route('create-campaign-target', [
            'company' => $this->company->id,
            'campaign' => $campaign->id,
            'target'   => $target->id
        ]), [
            'rules' => $target->rules
        ], $this->authHeaders());

        $response->assertStatus(400);
        $response->assertJSONStructure([
            'error'
        ]);
    }

    /**
    * Test updating a campaign target 
    *
    * @group feature-campaign-targets
    */
    public function testUpdate()
    {
        $campaign = $this->createCampaign([
            'type' => Campaign::TYPE_WEB
        ]);

        $target = factory(CampaignTarget::class)->create([
            'campaign_id' => $campaign->id
        ]);

        $updatedTarget = factory(CampaignTarget::class)->make([
            'rules' => json_encode([
                'DEVICE_TYPES' => [
                    'DESKTOP',
                ],
    
                'BROWSERS' => [
                    'GOOGLE_CHROME'
                ],
    
                'LOCATIONS' => [],
    
                'URL_RULES' => [
                    [
                        'name'   => 'UPDATED!!',
                        'driver' => 'CURRENT_URL',
                        'type'   => 'PATH',
                        'condition' => [
                            'type'  => 'EQUALS',
                            'key'   => '/home',
                            'value' => ''
                        ]
                    ]
                ]
            ])
        ]);

        $response = $this->json('PUT', route('update-campaign-target', [
            'company' => $this->company->id,
            'campaign' => $campaign->id,
            'target'   => $target->id
        ]), [
            'rules' => $updatedTarget->rules
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'updated',
            'campaign_target' => [
                'id'          => $target->id,
                'campaign_id' => $campaign->id,
                'rules'       => $updatedTarget->rules
            ],
        ]);

        $this->assertTrue( CampaignTarget::where('campaign_id', $campaign->id)->count() == 1);
    }

     /**
    * Test deleting a campaign target 
    *
    * @group feature-campaign-targets
    */
    public function testDelete()
    {
        $campaign = $this->createCampaign([
            'type' => Campaign::TYPE_WEB
        ]);

        $target = factory(CampaignTarget::class)->create([
            'campaign_id' => $campaign->id,
        ]);

        $response = $this->json('DELETE', route('delete-campaign-target', [
            'company' => $this->company->id,
            'campaign' => $campaign->id,
            'target'   => $target->id
        ]), [
           
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'deleted'
        ]);

        $this->assertTrue( CampaignTarget::where('campaign_id', $campaign->id)->count() == 0);
    }

    


}
