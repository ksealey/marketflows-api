<?php

namespace Tests\Feature\Company\Campaign;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\Company\Campaign;
use \App\Models\Company\CampaignDomain;

class DomainTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test creating a campaign domain
     * 
     * @group feature-campaign-domains
     */
    public function testCreate()
    {
        $user = $this->createUser();
        $campaign = $this->createCampaign([
            'type' => Campaign::TYPE_WEB
        ]);

        $domain = factory(CampaignDomain::class)->make();

        $response = $this->json('POST', route('create-campaign-domain', [
            'company' => $this->company->id,
            'campaign' => $campaign->id
        ]), [
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
     * Test creating a campaign domain with a non-web camapign
     * 
     * @group feature-campaign-domains
     */
    public function testCreateWithNonWebCampaign()
    {
        $user = $this->createUser();
        $campaign = $this->createCampaign([
            'type' => Campaign::TYPE_PRINT
        ]);

        $domain = factory(CampaignDomain::class)->make();

        $response = $this->json('POST', route('create-campaign-domain', [
            'company' => $this->company->id,
            'campaign' => $campaign->id
        ]), [
            'domain'     => $domain->domain
        ], $this->authHeaders());
        $response->assertStatus(400);
        $response->assertJSON([
            'error' => 'Only web campaigns can have associated domains'
        ]);
    }

    /**
     * Test updating a campaign domain
     * 
     * @group feature-campaign-domains
     */
    public function testUpdate()
    {
        $user = $this->createUser();
        $campaign = $this->createCampaign();

        $domain = factory(CampaignDomain::class)->create([
            'campaign_id' => $campaign->id,
        ]);

        $updatedDomain = factory(CampaignDomain::class)->make();

        $response = $this->json('PUT', route('update-campaign-domain', [
            'company' => $this->company->id,
            'campaign' => $campaign->id,
            'domain'   => $domain->id
        ]), [
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
     * @group feature-campaign-domains
     */
    public function testDelete()
    {
        $user = $this->createUser();
        $campaign = $this->createCampaign();

        $domain = factory(CampaignDomain::class)->create([
            'campaign_id' => $campaign->id,
        ]);

        $response = $this->json('DELETE', route('delete-campaign-domain', [
            'company' => $this->company->id,
            'campaign' => $campaign->id,
            'domain'   => $domain->id
        ]), [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'deleted'
        ]);

        $this->assertTrue(CampaignDomain::where('campaign_id', $campaign->id)->count() == 0);
    }
}
