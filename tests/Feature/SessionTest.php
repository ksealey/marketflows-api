<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Company\Campaign;

class SessionTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     *  Test creating a session as a public user without a campaign
     * 
     *  @group sessions
     */
    public function testCreateWithoutCampaign()
    {
        $response = $this->json('POST', 'http://localhost/v1/public/sessions', [
            'device_width' => 100,
            'device_height'=> 100
        ]);
        $response->assertStatus(200);
        
        $response->assertJSON([
            'session' => []
        ]);
    }

    /**
     *  Test creating a session as a public user
     * 
     *  @group sessions
     */
    public function testCreateWithCampaign()
    {
        $pool        = $this->createPhoneNumberPool();
        $phoneNumber = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id
        ]);

        $campaign = factory(Campaign::class)->make([
            'type'                  => Campaign::TYPE_WEB,
            'phone_number_pool_id'  => $pool->id,
        ]);

        $response = $this->json('POST', 'http://localhost/v1/public/sessions', [
            'device_width' => 100,
            'device_height'=> 100
        ]);

        $response->assertStatus(200);
        
        $response->assertJSON([
            'session' => []
        ]);
    }
}
