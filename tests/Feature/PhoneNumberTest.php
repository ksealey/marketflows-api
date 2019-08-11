<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

        $phone1 = factory(\App\Models\PhoneNumber::class)->create([
            'company_id'  => $user->company_id,
            'twilio_id'   => str_random(40),
            'created_by'  => $user->id
        ]);

        $phone2 = factory(\App\Models\PhoneNumber::class)->create([
            'company_id'  => $user->company_id,
            'twilio_id'   => str_random(40),
            'created_by'  => $user->id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/phone-numbers', [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'phone_numbers' => [
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
     * Test creating a phone number
     *
     * @group phone-numbers
     */
    public function testCreate()
    {
        $user = $this->createUser();

        $pool = factory(\App\Models\PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $phone = factory(\App\Models\PhoneNumber::class)->make([
            'country_code' => '1',
            'number'       => '5005550006'
        ]);

        $response = $this->json('POST', 'http://localhost/v1/phone-numbers', [
            'phone_number_pool' => $pool->id,
            'number'            => $phone->country_code . $phone->number,
            'name'              => $phone->name,
            'source'            => $phone->source,
            'forward_to_number' => $phone->forward_to_country_code . $phone->forward_to_number
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

        $pool = factory(\App\Models\PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $phone = factory(\App\Models\PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'phone_number_pool_id' =>$pool->id,
            'twilio_id'=> str_random(40)

        ]);

        $response = $this->json('GET', 'http://localhost/v1/phone-numbers/' . $phone->id, [], $this->authHeaders());

        $response->assertStatus(200);

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
     * Test updating a phone number
     *
     * @group phone-numbers
     */
    public function testUpdate()
    {
        $user = $this->createUser();

        $pool = factory(\App\Models\PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $phone = factory(\App\Models\PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'phone_number_pool_id' =>$pool->id,
            'twilio_id'=> str_random(40)
        ]);

        $newName   = 'UPDATED';
        $newSource = 'TRK_SOURCE_UPDATED';
        $newForwardTo = '8009099989';
        $newPool = factory(\App\Models\PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);
        $response = $this->json('PUT', 'http://localhost/v1/phone-numbers/' . $phone->id, [
            'name' => $newName,
            'source' => $newSource,
            'phone_number_pool' => $newPool->id,
            'forward_to_number' => $newForwardTo
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
                'forward_to_number'       => $newForwardTo 
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

        $pool = factory(\App\Models\PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $phone = factory(\App\Models\PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'phone_number_pool_id' =>$pool->id,
            'twilio_id'=> str_random(40)
        ]);

        $response = $this->json('DELETE', 'http://localhost/v1/phone-numbers/' . $phone->id, [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'deleted'
        ]);
    }
}
