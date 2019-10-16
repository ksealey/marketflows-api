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
     * @group feature-phone-number-pools
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

        $response = $this->json('GET', route('list-phone-number-pools', [
            'company' => $this->company->id
        ]), [], $this->authHeaders());
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
     * @group feature-phone-number-pools
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

        $response = $this->json('GET', route('list-phone-number-pools', [
            'company' => $this->company->id
        ]), [
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
     * @group feature-phone-number-pools
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

        $response = $this->json('POST', route('create-phone-number-pool', [
            'company' => $this->company->id
        ]), [
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
     * @group feature-phone-number-pools
     */
    public function testRead()
    {
        $user = $this->createUser();

        $pool = $this->createPhoneNumberPool([
            'company_id'  => $this->company->id,
            'created_by'  =>  $user->id
        ]);

        $response = $this->json('GET', route('read-phone-number-pool', [
            'company'         => $this->company->id,
            'phoneNumberPool' => $pool->id,
        ]), [], $this->authHeaders());

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
     * @group feature-phone-number-pools
     */
    public function testUpdate()
    {
        $user = $this->createUser();

        $pool = $this->createPhoneNumberPool([
            'company_id' => $this->company->id,
            'created_by' => $user->id
        ]);

        $updatedPool = factory(PhoneNumberPool::class)->make();

        $config = $this->createPhoneNumberConfig();

        $response = $this->json('PUT', route('update-phone-number-pool', [
            'company'         => $this->company->id,
            'phoneNumberPool' => $pool->id,
        ]), [
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
     * @group feature-phone-number-pools
     */
    public function testDelete()
    {
        $user = $this->createUser();

        $pool = $this->createPhoneNumberPool();

        $response = $this->json('DELETE',  route('delete-phone-number-pool', [
            'company'         => $this->company->id,
            'phoneNumberPool' => $pool->id,
        ]), [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'deleted',
        ]);

        $this->assertTrue(PhoneNumberPool::find($pool->id) == null);
    }


    /**
     * Test deleting a phone number pool that is linked to a campaign
     *
     * @group feature-phone-number-pools
     */
    public function testDeletePhonePoolLinkedToCampaign()
    {
        $campaign = $this->createCampaign();

        $pool = $this->createPhoneNumberPool([
            'campaign_id' => $campaign->id
        ]);

        $response = $this->json('DELETE', route('delete-phone-number-pool', [
            'company'         => $this->company->id,
            'phoneNumberPool' => $pool->id,
        ]), [], $this->authHeaders());
        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'This phone number pool is in use'
        ]);
    }
}
