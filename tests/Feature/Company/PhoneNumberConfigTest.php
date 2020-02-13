<?php

namespace Tests\Feature\Company;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Company\PhoneNumberConfig;
use App\Models\Company\AudioClip;

class PhoneNumberConfigTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test listing phone number configs
     * 
     * @group feature-phone-number-configs
     */
    public function testList()
    {
        $user = $this->createUser();

        $config1 = factory(PhoneNumberConfig::class)->create([
            'company_id'  => $this->company->id,
            'user_id'  => $user->id
        ]);

        $config2 = factory(PhoneNumberConfig::class)->create([
            'company_id'  => $this->company->id,
            'user_id'  => $user->id
        ]);

        $response = $this->json('GET', route('list-phone-number-configs', [
            'company' => $this->company->id
        ]), [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'success',
            'phone_number_configs'    => [
                [
                    'id' => $config1->id
                ],
                [
                    'id' => $config2->id
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
     * @group feature-phone-number-configs
     */
    public function testListWithFilter()
    {
        $user = $this->createUser();

        $config1 = factory(PhoneNumberConfig::class)->create([
            'company_id'  => $this->company->id,
            'user_id'  => $user->id
        ]);

        $config2 = factory(PhoneNumberConfig::class)->create([
            'company_id'  => $this->company->id,
            'user_id'  => $user->id
        ]);

        $response = $this->json('GET', route('list-phone-number-configs', [
            'company' => $this->company->id
        ]), [
            'search' => $config2->name
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'message'               => 'success',
            'phone_number_configs'  => [
                [
                    'id' => $config2->id
                ]
            ],
            'result_count'          => 1,
            'limit'                 => 25,
            'page'                  => 1,
            'total_pages'           => 1
        ]);
    }

    /**
     * Test creating a phone number config
     * 
     * @group feature-phone-number-configs
     */
    public function testCreate()
    {
        $user = $this->createUser();

        $config = factory(PhoneNumberConfig::class)->make();

        $audioClip = factory(AudioClip::class)->create([
            'company_id'  => $this->company->id,
            'user_id' => $user->id
        ]);

        $response = $this->json('POST', 'http://localhost/v1/companies/' . $this->company->id . '/phone-number-configs', [
            'name'              => $config->name,
            'source'            => $config->source,
            'forward_to_number' => $config->forward_to_number,
            'record'            => 1,
            'audio_clip'        => $audioClip->id,
            'whisper_message'   => $config->whisper_message,
            'whisper_language'  => $config->whisper_language,
            'whisper_voice'     => $config->whisper_voice,
        ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJSON([
            'phone_number_config' => [
                'name'              => $config->name,
                'source'            => $config->source,
                'forward_to_number' => $config->forward_to_number,
                'audio_clip_id'     => $audioClip->id,
                'whisper_message'   => $config->whisper_message,
                'whisper_language'  => $config->whisper_language,
                'whisper_voice'     => $config->whisper_voice,    
            ]
        ]);
    }

     /**
     * Test reading a phone number config
     * 
     * @group feature-phone-number-configs
     */
    public function testRead()
    {
        $user = $this->createUser();

        $config = factory(PhoneNumberConfig::class)->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id
        ]);

        $response = $this->json('GET', route('read-phone-number-config', [
            'company'           => $this->company->id,
            'phoneNumberConfig' => $config->id
        ]), [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'phone_number_config' => [
                'name'                  => $config->name,
                'source'                => $config->source,
                'forward_to_number'     => $config->forward_to_number,
                'whisper_message'       => $config->whisper_message,
                'whisper_language'      => $config->whisper_language,
                'whisper_voice'         => $config->whisper_voice,    
            ]
        ]);
    }

    /**
     * Test updating a phone number config
     * 
     * @group feature-phone-number-configs
     */
    public function testUpdate()
    {
        $user = $this->createUser();

        $audioClip = factory(AudioClip::class)->create([
            'company_id'  => $this->company->id,
            'user_id' => $user->id
        ]);

        $config = factory(PhoneNumberConfig::class)->create([
            'company_id'  => $this->company->id,
            'user_id' => $user->id,
        ]);

        $newConfig = factory(PhoneNumberConfig::class)->make();

        $response = $this->json('PUT', route('update-phone-number-config', [
            'company'           => $this->company->id,
            'phoneNumberConfig' => $config->id
        ]), [
            'name'              => $newConfig->name,
            'source'            => $newConfig->source,
            'forward_to_number' => $newConfig->forward_to_number,
            'record'            => 0,
            'audio_clip'        => $audioClip->id,
            'whisper_message'   => $newConfig->whisper_message,
            'whisper_language'  => $newConfig->whisper_language,
            'whisper_voice'     => $newConfig->whisper_voice,
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'phone_number_config' => [
                'audio_clip_id'         => $audioClip->id,
                'name'                  => $newConfig->name,
                'source'                => $newConfig->source,
                'forward_to_number'     => $newConfig->forward_to_number,
                'recording_enabled_at'  => null,
                'whisper_message'       => $newConfig->whisper_message,
                'whisper_language'      => $newConfig->whisper_language,
                'whisper_voice'         => $newConfig->whisper_voice,    
            ]
        ]);
    }

    /**
     * Test deleting a phone number config when in use
     * 
     * @group feature-phone-number-configs
     */
    public function testCannotDeleteWhenInUse()
    {
        $user  = $this->createUser();

        $config = factory(PhoneNumberConfig::class)->create([
            'company_id'  => $this->company->id,
            'user_id'  => $user->id,
        ]);

        $phone = $this->createPhoneNumber([
            'phone_number_config_id' => $config->id
        ]);

        $response = $this->json('DELETE', route('delete-phone-number-config', [
            'company'           => $this->company->id,
            'phoneNumberConfig' => $config->id
        ]), [], $this->authHeaders());

        $response->assertStatus(400);

        $response->assertJSONStructure([
            'error'
        ]);
    }

    /**
     * Test deleting a phone number config
     * 
     * @group feature-phone-number-configs
     */
    public function testDelete()
    {
        $user  = $this->createUser();

        $config = factory(PhoneNumberConfig::class)->create([
            'company_id'  => $this->company->id,
            'user_id'  => $user->id,
        ]);

        $response = $this->json('DELETE', route('delete-phone-number-config', [
            'company'           => $this->company->id,
            'phoneNumberConfig' => $config->id
        ]), [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'deleted'
        ]);

        $this->assertTrue(PhoneNumberConfig::find($config->id) == null);
    }
}
