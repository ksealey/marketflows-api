<?php

namespace Tests\Feature\Company\PhoneNumber;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Company\PhoneNumber\Call;

class CallTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test listing calls
     * 
     * @group calls
     */
    public function testListCalls()
    {
        $phoneNumber = $this->createPhoneNumber([
            'source' => 'TRACKING_SOURCE_' . mt_rand(999999, 999999999)
        ]);
        
        $call = factory(Call::class)->create([
            'phone_number_id' => $phoneNumber->id,
            'source'          => $phoneNumber->source,
            'to_number'       => $phoneNumber->number
        ]);

        $call2 = factory(Call::class)->create([
            'phone_number_id' => $phoneNumber->id,
            'source'          => $phoneNumber->source,
            'to_number'       => $phoneNumber->number
        ]);

        $response = $this->json('GET', 'http://localhost/v1/companies/' . $phoneNumber->company_id . '/phone-numbers/' . $phoneNumber->id . '/calls', [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'message'               => 'success',
            'calls'    => [
                [
                    'id' => $call->id
                ],
                [
                    'id' => $call2->id
                ]
            ],
            'result_count'          => 2,
            'limit'                 => 25,
            'page'                  => 1,
            'total_pages'           => 1
        ]);
    }

    /**
     * Test read call
     * 
     * @group calls
     */
    public function testReadCall()
    {
        $phoneNumber = $this->createPhoneNumber([
            'source' => 'TRACKING_SOURCE_' . mt_rand(999999, 999999999)
        ]);
        
        $call = factory(Call::class)->create([
            'phone_number_id' => $phoneNumber->id,
            'source'          => $phoneNumber->source,
            'to_number'       => $phoneNumber->number
        ]);

        $response = $this->json('GET', 'http://localhost/v1/companies/' . $phoneNumber->company_id . '/phone-numbers/' . $phoneNumber->id . '/calls/' . $call->id, [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'success',
            'call'    => [
                'id' => $call->id,
                'phone_number' => [
                    'id' => $phoneNumber->id,
                    'company' => [
                        'id' => $this->company->id
                    ]
                ]
            ]
        ]);
    }
}
