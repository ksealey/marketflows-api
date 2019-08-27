<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\Company\AudioClip;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\PhoneNumberPool;
use \App\Models\Company\Campaign;
use \App\Models\Company\CampaignPhoneNumber;
use \App\Models\Company\CampaignPhoneNumberPool;



class PhoneNumberTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test listing phone number
     *
     * @group phone-numbers
     */
    public function testList()
    {
        $user = $this->createUser();

        $phone1 = factory(PhoneNumber::class)->create([
            'company_id'  => $user->company_id,
            'twilio_id'   => str_random(40),
            'created_by'  => $user->id
        ]);

        $phone2 = factory(PhoneNumber::class)->create([
            'company_id'  => $user->company_id,
            'twilio_id'   => str_random(40),
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

        $phone1 = factory(PhoneNumber::class)->create([
            'company_id'  => $this->company->id,
            'twilio_id'   => str_random(40),
            'created_by'  => $user->id
        ]);

        $phone2 = factory(PhoneNumber::class)->create([
            'company_id'  => $this->company->id,
            'twilio_id'   => str_random(40),
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
     * @group phone-numbers-
     */
    public function testCreate()
    {
        $user = $this->createUser();

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $phone = factory(PhoneNumber::class)->make([
            'country_code' => '1',
            'number'       => '5005550006'
        ]);

        $audioClip = factory(AudioClip::class)->create([
            'company_id'  => $user->company_id,
            'created_by' => $user->id
        ]);

        $response = $this->json('POST', 'http://localhost/v1/companies/' . $this->company->id . '/phone-numbers', [
            'phone_number_pool' => $pool->id,
            'number'            => $phone->country_code . $phone->number,
            'name'              => $phone->name,
            'source'            => $phone->source,
            'forward_to_number' => $phone->forward_to_country_code . $phone->forward_to_number,
            'audio_clip'        => $audioClip->id
        ], $this->authHeaders());

        $response->assertStatus(201);

        $response->assertJson([
            'phone_number' => [
                'phone_number_pool_id' => $pool->id,
                'country_code'  => $phone->country_code,
                'number'        => $phone->number,
                'name'          => $phone->name,
                'source'        => $phone->source,
                'forward_to_country_code' => $phone->forward_to_country_code,
                'forward_to_number'       => $phone->forward_to_number 
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

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $audioClip = factory(AudioClip::class)->create([
            'company_id'  => $user->company_id,
            'created_by' => $user->id
        ]);

        $phone = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'phone_number_pool_id' =>$pool->id,
            'twilio_id'=> str_random(40),
            'audio_clip_id' => $audioClip->id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/companies/' . $this->company->id . '/phone-numbers/' . $phone->id, [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'phone_number' => [
                'phone_number_pool_id' => $pool->id,
                'country_code'  => $phone->country_code,
                'number'        => $phone->number,
                'name'          => $phone->name,
                'source'        => $phone->source,
                'forward_to_country_code' => $phone->forward_to_country_code,
                'forward_to_number'       => $phone->forward_to_number,
                'audio_clip_id' => $audioClip->id
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

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $phone = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'phone_number_pool_id' =>$pool->id,
            'twilio_id'=> str_random(40)
        ]);

        $newName   = 'UPDATED';
        $newSource = 'TRK_SOURCE_UPDATED';
        $newForwardTo = '8009099989';
        $newPool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);
        $newAudioClip = factory(AudioClip::class)->create([
            'company_id'  => $user->company_id,
            'created_by' => $user->id
        ]);

        $response = $this->json('PUT', 'http://localhost/v1/companies/' . $this->company->id . '/phone-numbers/' . $phone->id, [
            'name' => $newName,
            'source' => $newSource,
            'phone_number_pool' => $newPool->id,
            'forward_to_number' => $newForwardTo,
            'audio_clip' => $newAudioClip->id
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'phone_number' => [
                'phone_number_pool_id' => $newPool->id,
                'country_code'  => $phone->country_code,
                'number'        => $phone->number,
                'name'          => $newName,
                'source'        => $newSource,
                'forward_to_country_code' => null,
                'forward_to_number'       => $newForwardTo,
                'audio_clip_id' => $newAudioClip->id
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

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $phone = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'phone_number_pool_id' =>$pool->id,
            'twilio_id'=> str_random(40)
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

        $phone = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'twilio_id'=> str_random(40)
        ]);

        $campaign    = factory(Campaign::class)->create([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => date('Y-m-d H:i:s', strtotime('now -10 days')),
            'ends_at'      => date('Y-m-d H:i:s', strtotime('now +10 minutes')),
        ]);
    
        CampaignPhoneNumber::create([
            'campaign_id'     => $campaign->id,
            'phone_number_id' => $phone->id
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

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $phone = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'phone_number_pool_id' => $pool->id,
            'twilio_id'=> str_random(40)
        ]);

        $campaign = factory(Campaign::class)->create([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => date('Y-m-d H:i:s', strtotime('now -10 days')),
            'ends_at'      => date('Y-m-d H:i:s', strtotime('now +10 minutes')),
        ]);
    
        CampaignPhoneNumberPool::create([
            'campaign_id'          => $campaign->id,
            'phone_number_pool_id' => $pool->id
        ]);

        $response = $this->json('DELETE', 'http://localhost/v1/companies/' . $this->company->id . '/phone-numbers/' . $phone->id, [], $this->authHeaders());

        $response->assertStatus(400);

        $response->assertJson([
            'error' => 'This phone number is in use - please detach from all related entities and try again'
        ]);
    }
}
