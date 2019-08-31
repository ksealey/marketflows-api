<?php

namespace Tests\Feature\Company\Campaign;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\Company\CampaignSpend;
use DateTime;
use DateTimeZone;

class SpendTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test creating a campaign spend
     * 
     * @group campaign-spends
     */
    public function testCreate()
    {
        $user = $this->createUser();
        $campaign = $this->createCampaign();

        $spend = factory(CampaignSpend::class)->make();

        $response = $this->json('POST', 'http://localhost/v1/companies/' . $campaign->company_id . '/campaigns/' . $campaign->id . '/spends', [
            'from_date' => $spend->from_date,
            'to_date'   => $spend->to_date,
            'total'     => $spend->total
        ], $this->authHeaders());

        $userTZ   = new DateTimeZone($user->timezone);
        $newTZ    = new DateTimeZone('UTC');

        $fromDate = new DateTime($spend->from_date, $userTZ);
        $toDate   = new DateTime($spend->to_date, $userTZ);

        $fromDate->setTimezone($newTZ);
        $toDate->setTimezone($newTZ);

        $response->assertStatus(201);
        $response->assertJSON([
            'message' => 'created',
            'campaign_spend' => [
                'from_date' => $fromDate->format('Y-m-d H:i:s'),
                'to_date'   => $toDate->format('Y-m-d H:i:s'),
                'total'     => $spend->total,
            ]
        ]);
    }

    /**
     * Test updating a campaign spend
     * 
     * @group campaign-spends
     */
    public function testUpdate()
    {
        $user = $this->createUser();
        $campaign = $this->createCampaign();

        $spend = factory(CampaignSpend::class)->create([
            'campaign_id' => $campaign->id,
        ]);

        $updatedSpend = factory(CampaignSpend::class)->make();

        $response = $this->json('PUT', 'http://localhost/v1/companies/' . $campaign->company_id . '/campaigns/' . $campaign->id . '/spends/' . $spend->id, [
            'from_date' => $updatedSpend->from_date,
            'to_date'   => $updatedSpend->to_date,
            'total'     => $updatedSpend->total
        ], $this->authHeaders());
        $response->assertStatus(200);

        $userTZ   = new DateTimeZone($user->timezone);
        $newTZ    = new DateTimeZone('UTC');

        $updatedFromDate = new DateTime($updatedSpend->from_date, $userTZ);
        $updatedToDate   = new DateTime($updatedSpend->to_date, $userTZ);

        $updatedFromDate->setTimezone($newTZ);
        $updatedToDate->setTimezone($newTZ);
        
        $response->assertJSON([
            'message' => 'updated',
            'campaign_spend' => [
                'id'        => $spend->id,
                'from_date' => $updatedFromDate->format('Y-m-d H:i:s'),
                'to_date'   => $updatedToDate->format('Y-m-d H:i:s'),
                'total'     => $updatedSpend->total,
            ]
        ]);

        $this->assertTrue(CampaignSpend::where('campaign_id', $campaign->id)->count() == 1);
    }

    /**
     * Test deleting a campaign spend
     * 
     * @group campaign-spends
     */
    public function testDelete()
    {
        $user = $this->createUser();
        $campaign = $this->createCampaign();

        $spend = factory(CampaignSpend::class)->create([
            'campaign_id' => $campaign->id,
        ]);

        $response = $this->json('DELETE', 'http://localhost/v1/companies/' . $campaign->company_id . '/campaigns/' . $campaign->id . '/spends/' . $spend->id, [], $this->authHeaders());
        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'deleted'
        ]);

        $this->assertTrue(CampaignSpend::where('campaign_id', $campaign->id)->count() == 0);
    }
}
