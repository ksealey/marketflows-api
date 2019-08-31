<?php

namespace Tests\Feature\Company\Campaign;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\Company\CampaignDomain;

class DomainTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test creating a campaign domain
     * 
     * @group campaign-domains
     */
    public function testCreate()
    {
        $user = $this->createUser();
        $campaign = $this->createCampaign();

        $domain = factory(CampaignDomain::class)->make();

        $response = $this->json('POST', 'http://localhost/v1/companies/' . $campaign->company_id . '/campaigns/' . $campaign->id . '/domains', [
            'domain'     => $domain->domain
        ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJSON([
            'message' => 'created',
            'campaign_domain' => [
                'domain' => $domain->domain
            ]
        ]);
    }

    /**
     * Test updating a campaign domain
     * 
     * @group campaign-domains
     */
    public function testUpdate()
    {
        $user = $this->createUser();
        $campaign = $this->createCampaign();

        $domain = factory(CampaignDomain::class)->create([
            'campaign_id' => $campaign->id,
        ]);

        $updatedDomain = factory(CampaignDomain::class)->make();

        $response = $this->json('PUT', 'http://localhost/v1/companies/' . $campaign->company_id . '/campaigns/' . $campaign->id . '/domains/' . $domain->id, [
            'domain' => $updatedDomain->domain
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'updated',
            'campaign_domain' => [
                'id'     => $domain->id,
                'domain' => $updatedDomain->domain
            ]
        ]);

        $this->assertTrue(CampaignDomain::where('campaign_id', $campaign->id)->count() == 1);
    }

    /**
     * Test deleting a campaign domain
     * 
     * @group campaign-domains
     */
    public function testDelete()
    {
        $user = $this->createUser();
        $campaign = $this->createCampaign();

        $domain = factory(CampaignDomain::class)->create([
            'campaign_id' => $campaign->id,
        ]);

        $response = $this->json('DELETE', 'http://localhost/v1/companies/' . $campaign->company_id . '/campaigns/' . $campaign->id . '/domains/' . $domain->id, [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'deleted'
        ]);

        $this->assertTrue(CampaignDomain::where('campaign_id', $campaign->id)->count() == 0);
    }
}
