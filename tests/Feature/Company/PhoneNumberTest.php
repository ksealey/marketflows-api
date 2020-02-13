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
     * @group feature-phone-numbers
     */
    public function testList()
    {
        $user = $this->createUser();

        $phone1 = $this->createPhoneNumber([
            'company_id'  => $this->company->id,
            'external_id' => str_random(40),
            'user_id'  => $user->id
        ]);

        $phone2 = $this->createPhoneNumber([
            'company_id'  => $this->company->id,
            'external_id' => str_random(40),
            'user_id'  => $user->id
        ]);

        $response = $this->json('GET', route('list-phone-numbers', [
            'company' => $this->company->id
        ]), [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJson([
            'results'    => [
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
     * @group feature-phone-numbers
     */
    public function testListWithFilter()
    {
        $user = $this->createUser();

        $phone1 = $this->createPhoneNumber([
            'company_id'  => $this->company->id,
            'external_id'   => str_random(40),
            'user_id'  => $user->id
        ]);

        $phone2 = $this->createPhoneNumber([
            'company_id'  => $this->company->id,
            'external_id'   => str_random(40),
            'user_id'  => $user->id
        ]);

        $response = $this->json('GET', route('list-phone-numbers', [
            'company' => $this->company->id
        ]), [
            'search' => $phone2->name
        ], $this->authHeaders());
        $response->assertStatus(200);

        $response->assertJson([
            'results'    => [
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
     * Test creating a local phone number
     *
     * @group feature-phone-numbers
     */
    public function testCreateLocal()
    {
        $user         = $this->createUser();
        $magicNumbers = config('services.twilio.magic_numbers');

        $phone = factory(PhoneNumber::class)->make([
            'country_code' => '1',
            'number'       =>  substr($magicNumbers['available'], -10)
        ]);

        $audioClip = factory(AudioClip::class)->create([
            'company_id'  => $this->company->id,
            'user_id'  => $user->id
        ]);

        $config = $this->createPhoneNumberConfig([
            'audio_clip_id' => $audioClip->id
        ]);

        $postData = [
            'name'                  => $phone->name,
            'phone_number_config'   => $config->id,
            'source'                => $phone->source,
            'category'              => $phone->category,
            'sub_category'          => $phone->sub_category,
            'starts_with'           => $phone->number,
            'toll_free'             => false,
            'swap_rules'            => $phone->swap_rules
        ];

        //  Make sure it doesn't prompt for swap rules when it's no longer a website
        $response = $this->json('POST', route('create-phone-number', [
            'company' => $this->company->id
        ]), $postData, $this->authHeaders());

        $response->assertStatus(201);

        $response->assertJSON([
            'kind'          => 'PhoneNumber',
            'country_code'  => $phone->country_code,
            'number'        => $phone->number,
            'name'          => $phone->name,
            'category'      => $phone->category,
            'sub_category'  => $phone->sub_category,
            'swap_rules'    => $phone->swap_rules,
            'toll_free'     => 0
        ]);
    }

    /** 
     * Test creating a toll-free number
     *
     * @group feature-phone-numbers
     */
    public function testCreateTollFree()
    {
        $user         = $this->createUser();
        $magicNumbers = config('services.twilio.magic_numbers');

        $phone = factory(PhoneNumber::class)->make([
            'country_code' => '1',
            'number'       =>  substr($magicNumbers['available'], -10)
        ]);

        $audioClip = factory(AudioClip::class)->create([
            'company_id'  => $this->company->id,
            'user_id'  => $user->id
        ]);

        $config = $this->createPhoneNumberConfig([
            'audio_clip_id' => $audioClip->id
        ]);

        $postData = [
            'name'                  => $phone->name,
            'phone_number_config'   => $config->id,
            'source'                => $phone->source,
            'category'              => $phone->category,
            'sub_category'          => $phone->sub_category,
            'swap_rules'            => $phone->swap_rules,
            'toll_free'             => true
        ];

        //  Make sure it doesn't prompt for swap rules when it's no longer a website
        $response = $this->json('POST', route('create-phone-number', [
            'company' => $this->company->id
        ]), $postData, $this->authHeaders());

        $response->assertStatus(201);

        $response->assertJSON([
            'kind'          => 'PhoneNumber',
            'country_code'  => $phone->country_code,
            'number'        => $phone->number,
            'name'          => $phone->name,
            'category'      => $phone->category,
            'sub_category'  => $phone->sub_category,
            'swap_rules'    => $phone->swap_rules,
            'toll_free'     => 1
        ]);
    }

    /**
     * Test reading a phone number
     *
     * @group feature-phone-numbers
     */
    public function testRead()
    {
        $user = $this->createUser();

        $phone = $this->createPhoneNumber([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'external_id'=> str_random(40),
        ]);

        $response = $this->json('GET', route('read-phone-number', [
            'company'     => $this->company->id,
            'phoneNumber' => $phone->id
        ]), [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'kind'          => 'PhoneNumber',
            'country_code'  => $phone->country_code,
            'number'        => $phone->number,
            'name'          => $phone->name,
            'category'      => $phone->category,
            'sub_category'  => $phone->sub_category,
            'swap_rules'    => $phone->swap_rules
        ]);
    }


    /**
     * Test updating a phone number
     *
     * @group feature-phone-numbers
     */
    public function testUpdate()
    {
        $user = $this->createUser();

        $phone = $this->createPhoneNumber([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'external_id'=> str_random(40)
        ]);

        //  Create a new config and generate new phone data
        $newConfig  = $this->createPhoneNumberConfig();
        $newPhone   = factory(PhoneNumber::class)->make([
            'category'      => 'OFFLINE',
            'sub_category'  => 'TV',
            'swap_rules'    => ''
        ]);

        $response = $this->json('PUT', route('update-phone-number', [
            'company'     => $this->company->id,
            'phoneNumber' => $phone->id
        ]), [
            'name'                => $newPhone->name,
            'source'              => $newPhone->source,
            'category'            => $newPhone->category,
            'sub_category'        => $newPhone->sub_category,
            'swap_rules'          => $newPhone->swap_rules,
            'phone_number_config' => $newConfig->id
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'kind'                   => 'PhoneNumber',
            'country_code'           => $phone->country_code,
            'number'                 => $phone->number,
            'name'                   => $newPhone->name,
            'source'                 => $newPhone->source,
            'category'               => $newPhone->category,
            'sub_category'           => $newPhone->sub_category,
            'swap_rules'             => $newPhone->swap_rules,
            'phone_number_config_id' => $newConfig->id
        ]);
    }

    /**
     * Test deleting a phone number
     *
     * @group feature-phone-numbers
     */
    public function testDelete()
    {
        $user = $this->createUser();

        $phone = $this->createPhoneNumber([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'external_id'=> str_random(40)
        ]);

        $response = $this->json('DELETE', route('delete-phone-number', [
            'company'     => $this->company->id,
            'phoneNumber' => $phone->id
        ]), [], $this->authHeaders());
        
        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'deleted'
        ]);
    }

    /**
     * Test checking that local phone numbers are available
     * 
     * @group feature-phone-numbers
     */
    public function testCheckLocalNumbersAvailable()
    {
        $user = $this->createUser();

        $route = route('phone-numbers-available', [
            'company'     => $this->company->id
        ]);

        //  Try local
        $response = $this->json('GET', $route, [
            'toll_free'     => false,
            'starts_with'   => '813',
            'count'         => 2
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'available' => true,
            'count'     => 2,
            'toll_free' => false
        ]);

        //  Try local that doesn't exist
        $response = $this->json('GET', $route, [
            'toll_free'     => false,
            'starts_with'   => '000',
            'count'         => 2
        ], $this->authHeaders());

        $response->assertStatus(400);

        $response->assertJSON([
            'available' => false,
            'count'     => 0,
            'toll_free' => false
        ]);

        $response->assertJSONStructure([
            'error'
        ]);
    }

    /**
     * Test checking that toll-free phone numbers are available
     * 
     * @group feature-phone-numbers
     */
    public function testCheckTollFreeNumbersAvailable()
    {
        $user = $this->createUser();

        $route = route('phone-numbers-available', [
            'company'     => $this->company->id
        ]);

        //  Try toll-free
        $response = $this->json('GET', $route, [
            'toll_free'     => true,
            'count'         => 2,
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'available' => true,
            'count'     => 2,
            'toll_free' => true
        ]);

        //  Try toll-free that doesn't exist
        $response = $this->json('GET', $route, [
            'toll_free'     => true,
            'starts_with'   => '000',
            'count'         => 2
        ], $this->authHeaders());

        $response->assertStatus(400);

        $response->assertJSON([
            'available' => false,
            'count'     => 0,
            'toll_free' => true
        ]);

        $response->assertJSONStructure([
            'error'
        ]);
    }
}
