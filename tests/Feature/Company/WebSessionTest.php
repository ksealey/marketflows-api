<?php

namespace Tests\Feature\Company;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Company\Campaign;
use App\Models\Company\CampaignDomain;
use App\Models\WebSession;
use App\Models\WebProfile;
use App\Models\WebDevice;
use App\Models\WebProfileIdentity;
use \Tests\CreatesUser;

class WebSessionTest extends TestCase
{
    use WithFaker, CreatesUser;

    protected $endpoint = 'http://locahost/v1/web-sessions';

    /**
     * Test creating a web session without a domain
     *
     * @group feature-web-sessions
     */
    public function testCreateSessionWithoutADomain()
    {
        $response = $this->json('POST', $this->endpoint);
        $response->assertStatus(201);
        $this->assertHasRequiredCookies($response);


        //  Make sure a session is created without a phone number and campaign
        $session  = WebSession::orderBy('id', 'DESC')->first();

        $this->assertTrue($session->campaign_id === null);
        $this->assertTrue($session->campaign_domain_id === null);
        $this->assertTrue($session->phone_number_pool_id === null);
        $this->assertTrue($session->phone_number_id === null);
    }

    /**
     * Test creating a web session with a campaign domain
     *
     * @group feature-web-sessions
     */
    public function testCreateSessionWitCampaignDomainForInactiveCampaign()
    {
        $campaign = $this->createCampaign([
            'activated_at' => null
        ]);

        $campaignDomain = factory(CampaignDomain::class)->create([
            'campaign_id' => $campaign->id
        ]);

        $response = $this->json('POST', $this->endpoint, [
            'campaign_domain_uuid' => $campaignDomain->uuid
        ]);

        $this->assertHasRequiredCookies($response);

        $session  = WebSession::orderBy('id', 'DESC')->first();
    
        $this->assertTrue($session->campaign_id == $campaign->id);
        $this->assertTrue($session->campaign_domain_id == $campaignDomain->id);
        $this->assertTrue($session->phone_number_pool_id == null);
        $this->assertTrue($session->phone_number_id == null);
        $this->assertTrue($session->web_device_id != null);
    }

    /**
     * Test creating a web session with a campaign domain and a campaign that's active
     *
     * @group feature-web-sessions
     */
    public function testCreateSessionWitCampaignDomainForActiveCampaign()
    {
        $campaign = $this->createCampaign([
            'activated_at' => date('Y-m-d H:i:s'),
            'type'         => Campaign::TYPE_WEB
        ]);

        $campaignDomain = factory(CampaignDomain::class)->create([
            'campaign_id' => $campaign->id,        
        ]);

        $pool   = $this->createPhoneNumberPool([
            'campaign_id' => $campaign->id
        ]);

        $phoneNumber = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id
        ]);

        $response = $this->json('POST', $this->endpoint, [
            'campaign_domain_uuid' => $campaignDomain->uuid
        ]);

        $this->assertHasRequiredCookies($response);

        $session  = WebSession::orderBy('id', 'DESC')->first();
    
        $this->assertTrue($session->campaign_id == $campaign->id);
        $this->assertTrue($session->campaign_domain_id == $campaignDomain->id);
        $this->assertTrue($session->phone_number_pool_id == $pool->id);
        $this->assertTrue($session->phone_number_id == $phoneNumber->id);
        $this->assertTrue($session->web_profile_identity_id != null);
        $this->assertTrue($session->web_device_id != null);
    }

    /**
     * Test creating a web session with an existing session
     *
     * @group feature-web-sessions
     */
    public function testCreateSessionWitExistingSession()
    {
        $faker = $this->faker();

        $response = $this->call('POST', $this->endpoint, [], [
            'mkf_session_uuid' => $faker->uuid()
        ]);
        $response->assertStatus(400);
        $response->assertJSON([
            'error' => 'Session in progress'
        ]);
    }

    /**
     * Test creating a web session with an existing identity
     *
     * @group feature-web-sessions
     */
    public function testCreateSessionWithExistingProfileIdentity()
    {
        $profile         = factory(WebProfile::class)->create();
        $profileIdentity = factory(WebProfileIdentity::class)->create([
            'web_profile_id' => $profile->id
        ]); 
        $campaign = $this->createCampaign([
            'activated_at' => date('Y-m-d H:i:s'),
            'type'         => Campaign::TYPE_WEB
        ]);

        $campaignDomain = factory(CampaignDomain::class)->create([
            'campaign_id' => $campaign->id,        
        ]);

        $pool   = $this->createPhoneNumberPool([
            'campaign_id' => $campaign->id
        ]);

        $phoneNumber = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id
        ]);

        $response = $this->call('POST', $this->endpoint, [
            'campaign_domain_uuid' => $campaignDomain->uuid
        ], [
            'mkf_pi_uuid' => $profileIdentity->uuid
        ]);

        $this->assertHasRequiredCookies($response);

        $session  = WebSession::orderBy('id', 'DESC')->first();
    
        $this->assertTrue($session->campaign_id == $campaign->id);
        $this->assertTrue($session->campaign_domain_id == $campaignDomain->id);
        $this->assertTrue($session->phone_number_pool_id == $pool->id);
        $this->assertTrue($session->phone_number_id == $phoneNumber->id);
        $this->assertTrue($session->web_profile_identity_id == $profileIdentity->id);
        $this->assertTrue($session->web_device_id != null);

    }

    /**
     * Test creating a web session with an existing device
     *
     * @group feature-web-sessions-
     */
    public function testCreateSessionWithExistingDevice()
    {
        $profile         = factory(WebProfile::class)->create();
        $profileIdentity = factory(WebProfileIdentity::class)->create([
            'web_profile_id' => $profile->id
        ]); 
        $device  = factory(WebDevice::class)->create([
            'web_profile_identity_id' => $profileIdentity->id
        ]);
        $campaign = $this->createCampaign([
            'activated_at' => date('Y-m-d H:i:s'),
            'type'         => Campaign::TYPE_WEB
        ]);

        $campaignDomain = factory(CampaignDomain::class)->create([
            'campaign_id' => $campaign->id,        
        ]);

        $pool   = $this->createPhoneNumberPool([
            'campaign_id' => $campaign->id
        ]);

        $phoneNumber = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id
        ]);

        $response = $this->call('POST', $this->endpoint, [
            'campaign_domain_uuid' => $campaignDomain->uuid
        ], [
            'mkf_pi_uuid' => $profileIdentity->uuid,
            'mkf_device_uuid' => $device->uuid
        ]);

        $this->assertHasRequiredCookies($response);

        $session  = WebSession::orderBy('id', 'DESC')->first();
    
        $this->assertTrue($session->campaign_id == $campaign->id);
        $this->assertTrue($session->campaign_domain_id == $campaignDomain->id);
        $this->assertTrue($session->phone_number_pool_id == $pool->id);
        $this->assertTrue($session->phone_number_id == $phoneNumber->id);
        $this->assertTrue($session->web_profile_identity_id == $profileIdentity->id);
        $this->assertTrue($session->web_device_id == $device->id);
    }

    /**
     * Test creating a web session with an existing device
     *
     * @group feature-web-sessions
     */
    public function testCreateSessionRotatesPhoneNumbers()
    {
        $profile         = factory(WebProfile::class)->create();
        $profileIdentity = factory(WebProfileIdentity::class)->create([
            'web_profile_id' => $profile->id
        ]); 
        $device  = factory(WebDevice::class)->create([
            'web_profile_identity_id' => $profileIdentity->id
        ]);
        $campaign = $this->createCampaign([
            'activated_at' => date('Y-m-d H:i:s'),
            'type'         => Campaign::TYPE_WEB
        ]);

        $campaignDomain = factory(CampaignDomain::class)->create([
            'campaign_id' => $campaign->id,        
        ]);

        $pool   = $this->createPhoneNumberPool([
            'campaign_id'              => $campaign->id,
            'auto_provision_enabled_at' => null
        ]);

        $phoneNumber = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id
        ]);

        $phoneNumber2 = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id
        ]);

        $phoneNumber3 = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id
        ]);

        $response = $this->call('POST', $this->endpoint, [
            'campaign_domain_uuid' => $campaignDomain->uuid
        ], [
            'mkf_pi_uuid' => $profileIdentity->uuid,
            'mkf_device_uuid' => $device->uuid
        ]);

        $this->assertHasRequiredCookies($response);

        $session  = WebSession::orderBy('id', 'DESC')->first();
    
        $this->assertTrue($session->campaign_id == $campaign->id);
        $this->assertTrue($session->campaign_domain_id == $campaignDomain->id);
        $this->assertTrue($session->phone_number_pool_id == $pool->id);
        $this->assertTrue($session->phone_number_id == $phoneNumber->id);
        $this->assertTrue($session->web_profile_identity_id == $profileIdentity->id);
        $this->assertTrue($session->web_device_id == $device->id);
    }

    public function assertHasRequiredCookies($response)
    {
        $response->assertCookie('mkf_session_uuid');
        $response->assertCookie('mkf_session_history');
        $response->assertCookie('mkf_session_phone');
        $response->assertCookie('mkf_pi_uuid');
        $response->assertCookie('mkf_device_uuid');
    }



}
