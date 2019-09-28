<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \Tests\CreatesUser;

class WebSessionTest extends TestCase
{
    use WithFaker, CreatesUser;

    /**
     * Test creating a web session without a domain
     *
     * @group web-sessions
     */
    public function testCreateSessionWithoutDomain()
    {
        $response = $this->json('POST', 'http://locahost/v1/web-sessions');

        $response->assertStatus(400);
        $response->assertJSONStructure([
            'error'
        ]);
    }

    /**
     * Test creating a web session with a domain but without a campaign
     * The session should still be created but without an assigned phone number
     * 
     * @group web-sessions
     */
    public function testCreateSessionWithDomainWithoutCampaign()
    {
        $faker = $this->faker();

        $response = $this->json('POST', 'http://locahost/v1/web-sessions', [
            'domain' => $faker->domainName()
        ]);

        $response->assertJSON([
            'session' => [
                'phone_number' => null,
                'campaign'     => null
            ]
        ]);
        $response->assertCookie('mkf_session');
        $response->assertStatus(201);
    }

    /**
     * Test creating a web session with a domain and a campaign(inactive)
     * The session should be created but without an assigned phone number
     * 
     * @group web-sessions
     */
    public function testCreateSessionWithDomainAndCampaignWhereInactive()
    {
        $campaignData = $this->createWebCampaign();

        $this->assertTrue(true);

        $faker = $this->faker();

        $response = $this->json('POST', 'http://locahost/v1/web-sessions', [
            'domain' => $faker->domainName()
        ]);

        $response->assertJSON([
            'session' => [
                'phone_number' => null,
                'campaign'     => null
            ]
        ]);
        $response->assertCookie('mkf_session');
        $response->assertStatus(201);
    }
}
