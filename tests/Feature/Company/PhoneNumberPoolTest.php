<?php

namespace Tests\Feature\Company;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\Company\PhoneNumberPool;
use \App\Models\Company\AudioClip;
use \App\Models\Company\Campaign;

class PhoneNumberPoolTest extends TestCase
{
    use \Tests\CreatesUser, RefreshDatabase;

    /**
     * Test listing phone number pools
     *
     * @group phone-number-pools
     */
    public function testList()
    {
        $user = $this->createUser();

        $pool = $this->createPhoneNumberPool([
            'company_id'  => $this->company->id,
            'created_by'  => $user->id
        ]);

        $pool2 = $this->createPhoneNumberPool([
            'company_id'  => $this->company->id,
            'created_by'  => $user->id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/companies/' . $this->company->id . '/phone-number-pools', [], $this->authHeaders());
        $response->assertStatus(200);

        $response->assertJson([
            'message'               => 'success',
            'phone_number_pools'    => [
                [
                    'id' => $pool->id
                ],
                [
                    'id' => $pool2->id
                ]
            ],
            'result_count'          => 2,
            'limit'                 => 25,
            'page'                  => 1,
            'total_pages'           => 1
        ]);
    }

    /**
     * Test listing phone number pools with a filter
     *
     * @group phone-number-pools
     */
    public function testListWithFilter()
    {
        $user = $this->createUser();

        $pool = $this->createPhoneNumberPool([
            'company_id'  => $this->company->id,
            'created_by'  => $user->id
        ]);

        $pool2 = $this->createPhoneNumberPool([
            'company_id'  => $this->company->id,
            'created_by'  => $user->id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/companies/' . $this->company->id . '/phone-number-pools', [
            'search' => $pool2->name
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'message'               => 'success',
            'phone_number_pools'    => [
                [
                    'id' => $pool2->id
                ]
            ],
            'result_count'          => 1,
            'limit'                 => 25,
            'page'                  => 1,
            'total_pages'           => 1
        ]);
    }

    /**
     * Test creating an phone number pool
     * 
     * @group phone-number-pools
     */
    public function testCreate()
    {
        $user = $this->createUser();

        $audioClip = factory(AudioClip::class)->create([
            'company_id'  => $this->company->id,
            'created_by'  =>  $user->id
        ]);

        $config = $this->createPhoneNumberConfig();

        $pool = factory(PhoneNumberPool::class)->make();

        $response = $this->json('POST', 'http://localhost/v1/companies/' . $this->company->id . '/phone-number-pools', [
            'audio_clip' => $audioClip->id,
            'name'       => $pool->name,
            'phone_number_config' => $config->id
        ], $this->authHeaders());

        $response->assertStatus(201);

        $response->assertJson([
            'message' => 'created',
            'phone_number_pool' => [
                'name' => $pool->name
            ]
        ]);
    }

    /**
     * Test reading an phone number pool
     * 
     * @group phone-number-pools
     */
    public function testRead()
    {
        $user = $this->createUser();

        $pool = $this->createPhoneNumberPool([
            'company_id'  => $this->company->id,
            'created_by'  =>  $user->id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/companies/' . $this->company->id . '/phone-number-pools/' . $pool->id, [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'message'           => 'success',
            'phone_number_pool' => [
                'id' => $pool->id
            ]
        ]);
    }

    /**
     * Test updating an phone number pool
     * 
     * @group phone-number-pools
     */
    public function testUpdate()
    {
        $user = $this->createUser();

        $pool = $this->createPhoneNumberPool([
            'company_id'  => $user->company_id,
            'created_by' => $user->id
        ]);

        $updatedPool = factory(PhoneNumberPool::class)->make();

        $config = $this->createPhoneNumberConfig();

        $response = $this->json('PUT', 'http://localhost/v1/companies/' . $this->company->id . '/phone-number-pools/' . $pool->id, [
            'name'   => $updatedPool->name,
            'phone_number_config' => $config->id
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'message'           => 'updated',
            'phone_number_pool' => [
                'id'     => $pool->id,
                'name'   => $updatedPool->name,
            ]
        ]);

        $this->assertTrue(PhoneNumberPool::find($pool->id)->name == $updatedPool->name);
    }

    /**
     * Test deleting a phone number pool
     * 
     * @group phone-number-pools
     */
    public function testDelete()
    {
        $user = $this->createUser();

        $pool = $this->createPhoneNumberPool([
            'company_id'  => $user->company_id,
            'created_by' => $user->id
        ]);

        $response = $this->json('DELETE',  'http://localhost/v1/companies/' . $this->company->id . '/phone-number-pools/' . $pool->id, [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'deleted',
        ]);

        $this->assertTrue(PhoneNumberPool::find($pool->id) == null);
    }


    /**
     * Test deleting a phone number pool that is linked to a campaign
     *
     * @group phone-number-pools
     */
    public function testDeletePhonePoolLinkedToCampaign()
    {
        $user = $this->createUser();

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $campaign = factory(Campaign::class)->create([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => date('Y-m-d H:i:s', strtotime('now -10 minutes')),
            'phone_number_pool_id' => $pool->id
        ]);

        $response = $this->json('DELETE',  'http://localhost/v1/companies/' . $this->company->id . '/phone-number-pools/' . $pool->id, [], $this->authHeaders());
        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'This phone number pool is in use'
        ]);
    }
}
