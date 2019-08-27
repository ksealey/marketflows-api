<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\CampaignPhoneNumberPool;
use \App\Models\PhoneNumberPool;
use \App\Models\Company\AudioClip;
use \App\Models\Campaign;

class PhoneNumberPoolTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test listing phone number pools
     *
     * @group phone-number-pool
     */
    public function testList()
    {
        $user = $this->createUser();

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id'  => $user->company_id,
            'created_by'  => $user->id
        ]);

        $pool2 = factory(PhoneNumberPool::class)->create([
            'company_id'  => $user->company_id,
            'created_by'  => $user->id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/phone-number-pools', [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'phone_number_pools' => [
                [
                    'id'
                ],
                [
                    'id'
                ],
            ]
        ]);
        $response->assertJson([
            'message'      => 'success',
            'result_count' => 2,
            'total_count'  => 2
        ]);
    }

    /**
     * Test creating an phone number pool
     * 
     * @group phone-number-pool
     */
    public function testCreate()
    {
        $user = $this->createUser();

        $audioClip = factory(\App\Models\AudioClip::class)->create([
            'company_id'  => $user->company_id,
            'created_by' => $user->id
        ]);

        $pool = factory(PhoneNumberPool::class)->make();

        $response = $this->json('POST', 'http://localhost/v1/phone-number-pools', [
            'audio_clip'                => $audioClip->id,
            'name'                      => $pool->name,
            'source'                    => $pool->source,
            'forward_to_country_code'   => $pool->forward_to_country_code,
            'forward_to_number'         => $pool->forward_to_number,
        ], $this->authHeaders());

        $response->assertStatus(201);

        $response->assertJsonStructure([
            'message',
            'phone_number_pool' => [
                'id'
            ]
        ]);
    }

    /**
     * Test reading an phone number pool
     * 
     * @group phone-number-pool
     */
    public function testRead()
    {
        $user = $this->createUser();

        $pool = factory(\App\Models\PhoneNumberPool::class)->create([
            'company_id'  => $user->company_id,
            'created_by' => $user->id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/phone-number-pools/' . $pool->id, [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'success',
            'phone_number_pool' => [
                'id' => $pool->id
            ]
        ]);
    }

    /**
     * Test updating an phone number pool
     * 
     * @group phone-number-pool
     */
    public function testUpdate()
    {
        $user = $this->createUser();

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id'  => $user->company_id,
            'created_by' => $user->id
        ]);

        $newPoolName = 'Updated pool name';

        $response = $this->json('PUT', 'http://localhost/v1/phone-number-pools/' . $pool->id, [
            'name'                      => $newPoolName,
            'source'                    => $pool->source,
            'forward_to_country_code'   => $pool->forward_to_country_code,
            'forward_to_number'         => $pool->forward_to_number,
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'message',
            'phone_number_pool' => [
                'id'
            ]
        ]);

        $this->assertTrue(PhoneNumberPool::find($pool->id)->name == $newPoolName);
    }

    /**
     * Test deleting a phone number pool
     * 
     * @group phone-number-pool
     */
    public function testDelete()
    {
        $user = $this->createUser();

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id'  => $user->company_id,
            'created_by' => $user->id
        ]);

        $response = $this->json('DELETE', 'http://localhost/v1/phone-number-pools/' . $pool->id, [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'deleted',
        ]);

        $this->assertTrue(PhoneNumberPool::find($pool->id) == null);
    }


    /**
     * Test deleting a phone number pool that is linked to a campaign
     *
     * @group phone-number-pool
     */
    public function testDeletePhonePoolLinkedToCampaign()
    {
        $user = $this->createUser();

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $campaign    = factory(Campaign::class)->create([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => date('Y-m-d H:i:s', strtotime('now -10 days')),
            'ends_at'      => date('Y-m-d H:i:s', strtotime('now +10 minutes')),
        ]);
    
        CampaignPhoneNumberPool::create([
            'campaign_id'          => $campaign->id,
            'phone_number_pool_id' => $pool->id
        ]);

        $response = $this->json('DELETE', 'http://localhost/v1/phone-number-pools/' . $pool->id, [], $this->authHeaders());

        $response->assertStatus(400);

        $response->assertJson([
            'error' => 'This phone number pool is in use - please detach from all related entities and try again'
        ]);
    }
}
