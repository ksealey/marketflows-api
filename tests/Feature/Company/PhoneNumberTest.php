<?php

namespace Tests\Feature\Company;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\Company\AudioClip;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\PhoneNumberPool;
use \App\Models\Company\Campaign;

class PhoneNumberTest extends TestCase
{
    use \Tests\CreatesUser, RefreshDatabase;

    /**
     * Test listing phone number
     *
     * @group phone-numbers
     */
    public function testList()
    {
        $user = $this->createUser();

        $phone1 = $this->createPhoneNumber([
            'company_id'  => $user->company_id,
            'external_id'   => str_random(40),
            'created_by'  => $user->id
        ]);

        $phone2 = $this->createPhoneNumber([
            'company_id'  => $user->company_id,
            'external_id'   => str_random(40),
            'created_by'  => $user->id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/companies/' . $this->company->id . '/phone-numbers', [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJson([
            'message'               => 'success',
            'phone_numbers'    => [
                [
                    'id' => $phone1->id
                ],
                [
                    'id' => $phone2->id
                ]
            ],
            'result_count'          => 2,
            'limit'                 => 25,
            'page'                  => 1,
            'total_pages'           => 1
        ]);
    }

    /**
     * Test listing phone number with a filter
     *
     * @group phone-numbers
     */
    public function testListWithFilter()
    {
        $user = $this->createUser();

        $phone1 = $this->createPhoneNumber([
            'company_id'  => $this->company->id,
            'external_id'   => str_random(40),
            'created_by'  => $user->id
        ]);

        $phone2 = $this->createPhoneNumber([
            'company_id'  => $this->company->id,
            'external_id'   => str_random(40),
            'created_by'  => $user->id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/companies/' . $this->company->id . '/phone-numbers', [
            'search' => $phone2->name
        ], $this->authHeaders());
        $response->assertStatus(200);

        $response->assertJson([
            'message'               => 'success',
            'phone_numbers'    => [
                [
                    'id' => $phone2->id
                ]
            ],
            'result_count'          => 1,
            'limit'                 => 25,
            'page'                  => 1,
            'total_pages'           => 1
        ]);
    }


    /** 
     * Test creating a phone number
     *
     * @group phone-numbers
     */
    public function testCreate()
    {
        $magicNumbers = config('services.twilio.magic_numbers');

        PhoneNumber::testing();

        $user = $this->createUser();

        $pool = $this->createPhoneNumberPool([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $phone = factory(PhoneNumber::class)->make([
            'country_code' => '1',
            'number'       =>  $magicNumbers['available']
        ]);

        $audioClip = factory(AudioClip::class)->create([
            'company_id'  => $user->company_id,
            'created_by' => $user->id
        ]);

        $response = $this->json('POST', '/v1/companies/' . $this->company->id . '/phone-numbers', [
            'phone_number_pool' => $pool->id,
            'phone_number_config' => $pool->phone_number_config_id,
            'number'            => $phone->country_code . $phone->number,
            'name'              => $phone->name
        ], $this->authHeaders());

        $response->assertStatus(201);

        $response->assertJSON([
            'phone_number' => [
                'phone_number_pool_id' => $pool->id,
                'country_code'  => $phone->country_code,
                'number'        => $phone->number,
                'name'          => $phone->name
            ]
        ]);
    }

    /**
     * Test reading a phone number
     *
     * @group phone-numbers
     */
    public function testRead()
    {
        $user = $this->createUser();

        $pool = $this->createPhoneNumberPool([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $audioClip = factory(AudioClip::class)->create([
            'company_id'  => $user->company_id,
            'created_by' => $user->id
        ]);

        $phone = $this->createPhoneNumber([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'phone_number_pool_id' =>$pool->id,
            'external_id'=> str_random(40),
        ]);

        $response = $this->json('GET', 'http://localhost/v1/companies/' . $this->company->id . '/phone-numbers/' . $phone->id, [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'phone_number' => [
                'phone_number_pool_id' => $pool->id,
                'country_code'  => $phone->country_code,
                'number'        => $phone->number,
                'name'          => $phone->name
            ]
        ]);
    }


    /**
     * Test updating a phone number
     *
     * @group phone-numbers
     */
    public function testUpdate()
    {
        $user = $this->createUser();

        $pool = $this->createPhoneNumberPool([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $phone = $this->createPhoneNumber([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'phone_number_pool_id' =>$pool->id,
            'external_id'=> str_random(40)
        ]);

        $newName   = 'UPDATED';
        $newPool = $this->createPhoneNumberPool([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);


        $response = $this->json('PUT', 'http://localhost/v1/companies/' . $this->company->id . '/phone-numbers/' . $phone->id, [
            'name' => $newName,
            'phone_number_config' => $phone->phone_number_config_id,
            'phone_number_pool' => $newPool->id,
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'phone_number' => [
                'phone_number_pool_id' => $newPool->id,
                'country_code'  => $phone->country_code,
                'number'        => $phone->number,
                'name'          => $newName
            ]
        ]);
    }

    /**
     * Test deleting a phone number
     *
     * @group phone-numbers
     */
    public function testDelete()
    {
        $user = $this->createUser();

        $pool = $this->createPhoneNumberPool([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $phone = $this->createPhoneNumber([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'external_id'=> str_random(40)
        ]);

        $response = $this->json('DELETE', 'http://localhost/v1/companies/' . $this->company->id . '/phone-numbers/' . $phone->id, [], $this->authHeaders());
        
        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'deleted'
        ]);
    }

    /**
     * Test deleting a phone number that is linked to a campaign
     *
     * @group phone-numbers
     */
    public function testDeletePhoneLinkedToCampaign()
    {
        $user = $this->createUser();

        $campaign    = $this->createCampaign([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => date('Y-m-d H:i:s', strtotime('now -10 days')),
        ]);

        $phone = $this->createPhoneNumber([
            'campaign_id'=> $campaign->id,
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'external_id'=> str_random(40)
        ]);

        $response = $this->json('DELETE', 'http://localhost/v1/companies/' . $this->company->id . '/phone-numbers/' . $phone->id, [], $this->authHeaders());

        $response->assertStatus(400);

        $response->assertJson([
            'error' => 'This phone number is in use - please detach from all related entities and try again'
        ]);
    }


    /**
     * Test deleting a phone number that is linked to a campaign via pool
     *
     * @group phone-numbers
     */
    public function testDeletePhoneLinkedToCampaignViaPool()
    {
        $user = $this->createUser();

        $campaign = $this->createCampaign([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => date('Y-m-d H:i:s', strtotime('now -10 days')),
        ]);

        $pool = $this->createPhoneNumberPool([
            'campaign_id' => $campaign->id,
            'company_id'  => $user->company_id,
            'created_by'  => $user->id
        ]);

        $phone = $this->createPhoneNumber([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'phone_number_pool_id' => $pool->id,
            'external_id'=> str_random(40)
        ]);
    
        $response = $this->json('DELETE', 'http://localhost/v1/companies/' . $this->company->id . '/phone-numbers/' . $phone->id, [], $this->authHeaders());

        $response->assertStatus(400);

        $response->assertJson([
            'error' => 'This phone number is in use - please detach from all related entities and try again'
        ]);
    }
}
