<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Company;
use App\Models\Company\PhoneNumberConfig;
use App\Models\Company\PhoneNumber;
use App\Models\Company\AudioClip;
use Storage;

class PhoneNumberConfigTest extends TestCase
{
    use \Tests\CreatesAccount;
   
    /**
     * Test creating a phone number config
     * 
     * @group phone-number-configs
     */
    public function testCreatePhoneNumberConfig()
    {
            $company    = $this->createCompany();
            $configData = factory(PhoneNumberConfig::class)->make([
                  'recording_enabled'     => 1, 
                  'transcription_enabled' => 1,

                  'keypress_enabled'      => 1,
                  'keypress_key'          => 0,
                  'keypress_attempts'     => 1,
                  'keypress_timeout'      => 5,
                  'keypress_message_type' => 'TEXT',
                  'keypress_message'      => 'Hello World',

                  'greeting_enabled'      => 1,
                  'greeting_message_type' => 'TEXT',
                  'greeting_message'      => 'Hello'
            ]);

            $response = $this->json('POST', route('create-phone-number-config', [
                  'company' => $company->id
            ]), [
                  "name"                  => $configData->name,
                  "forward_to_number"     => $configData->forward_to_number,
                  "greeting_enabled"      => $configData->greeting_enabled,
                  "greeting_message_type" => $configData->greeting_message_type,
                  "greeting_audio_clip_id"=> $configData->greeting_audio_clip_id,
                  "greeting_message"      => $configData->greeting_message,
                  "whisper_enabled"       => $configData->whisper_enabled,
                  "whisper_message"       => $configData->whisper_message,
                  "transcription_enabled" => $configData->transcription_enabled,
                  "recording_enabled"     => $configData->recording_enabled,
                  "keypress_enabled"      => $configData->keypress_enabled,
                  "keypress_key"          => $configData->keypress_key,
                  "keypress_attempts"     => $configData->keypress_attempts,
                  "keypress_timeout"      => $configData->keypress_timeout,
                  "keypress_message_type" => $configData->keypress_message_type,
                  "keypress_audio_clip_id"=> $configData->keypress_audio_clip_id,
                  "keypress_message"      => $configData->keypress_message,
                  "transcription_enabled" => $configData->transcription_enabled,
            ]);
            
            $response->assertStatus(201);
            $response->assertJSON([
                  "id"                    => $response['id'],
                  "account_id"            => $company->account_id,
                  "company_id"            => $company->id,
                  "name"                  => $configData->name,
                  "forward_to_number"     => $configData->forward_to_number,
                  "greeting_enabled"      => !!$configData->greeting_enabled,
                  "greeting_message_type" => $configData->greeting_message_type,
                  "greeting_audio_clip_id"=> $configData->greeting_audio_clip_id,
                  "greeting_message"      => $configData->greeting_message,
                  "whisper_enabled"       => !!$configData->whisper_enabled,
                  "whisper_message"       => $configData->whisper_message,
                  "recording_enabled"     => !!$configData->recording_enabled,
                  "transcription_enabled" => !!$configData->transcription_enabled,
                  "keypress_enabled"      => !!$configData->keypress_enabled,
                  "keypress_key"          => $configData->keypress_key,
                  "keypress_attempts"     => $configData->keypress_attempts,
                  "keypress_timeout"      => $configData->keypress_timeout,
                  "keypress_message_type" => $configData->keypress_message_type,
                  "keypress_audio_clip_id"=> $configData->keypress_audio_clip_id,
                  "keypress_message"      => $configData->keypress_message,
                  "created_by"            => $this->user->id,
                  "link"                  => route('read-phone-number-config', [
                                                'company'           => $company->id,
                                                'phoneNumberConfig' => $response['id']
                                          ]),
                  "kind"                  => "PhoneNumberConfig"
            ]);

            $this->assertDatabaseHas('phone_number_configs', [
                  'id' => $response['id'],
            ]);
    }

    /**
     * Test viewing phone number config
     * 
     * @group phone-number-configs
     */
    public function testReadPhoneNumberConfig()
    {
            $company = $this->createCompany();
            $config  = factory(PhoneNumberConfig::class)->create([
                  'account_id' => $company->account_id,
                  'company_id' => $company->id,
                  'created_by' => $this->user->id
            ]);

            $response = $this->json('GET', route('read-phone-number-config', [
                  'company' => $company->id,
                  'phoneNumberConfig' => $config->id
            ]));

            $response->assertJSON([
                  "id"                    => $config->id,
                  "account_id"            => $company->account_id,
                  "company_id"            => $company->id,
                  "name"                  => $config->name,
                  "forward_to_number"     => $config->forward_to_number,
                  "greeting_enabled"      => !!$config->greeting_enabled,
                  "greeting_audio_clip_id"=> $config->greeting_audio_clip_id,
                  "greeting_message_type" => $config->greeting_message_type,
                  "greeting_message"      => $config->greeting_message,
                  "whisper_enabled"       => !!$config->whisper_enabled,
                  "whisper_message"       => $config->whisper_message,
                  "recording_enabled"     => !!$config->recording_enabled,
                  "transcription_enabled" => !!$config->transcription_enabled,
                  "keypress_enabled"      => !!$config->keypress_enabled,
                  "keypress_key"          => $config->keypress_key,
                  "keypress_attempts"     => $config->keypress_attempts,
                  "keypress_timeout"      => $config->keypress_timeout,
                  "keypress_message_type" => $config->keypress_message_type,
                  "keypress_audio_clip_id"=> $config->keypress_audio_clip_id,
                  "keypress_message"      => $config->keypress_message,
                  "created_by"            => $this->user->id,
                  "link"                  => route('read-phone-number-config', [
                                                'company'           => $company->id,
                                                'phoneNumberConfig' => $config->id
                                          ]),
                  "kind"                  => "PhoneNumberConfig"
            ]);
    }

    /**
     * Test listing phone number configs
     * 
     * @group phone-number-configs
     */
    public function testListPhoneNumberConfig()
    {
            $count = mt_rand(5, 15);
            $company = $this->createCompany();
            $config  = factory(PhoneNumberConfig::class, $count)->create([
                  'account_id' => $company->account_id,
                  'company_id' => $company->id,
                  'created_by' => $this->user->id
            ]);

            $response = $this->json('GET', route('list-phone-number-configs', [
                  'company' => $company->id,
                  'date_type' => 'ALL_TIME'
            ]));

            $response->assertJSON([
                  "result_count" => $count,
                  "limit" => 250,
                  "page" => 1,
                  "total_pages" => 1,
                  "next_page" => null,
            ]);

            $response->assertJSONStructure([
                 'results' => [
                       [
                             'id',
                             'name',
                       ]
                 ]
            ]);
    }

    /**
     * Test updating phone number config
     * 
     * @group phone-number-configs--
     */
    public function testUpdatePhoneNumberConfig()
    {
            $company = $this->createCompany();
            $config  = factory(PhoneNumberConfig::class)->create([
                  'account_id' => $company->account_id,
                  'company_id' => $company->id,
                  'created_by' => $this->user->id,
                  
                  'greeting_enabled'      => 1,
                  'greeting_message_type' => 'AUDIO',
                  'greeting_message'      => null,

                  'keypress_enabled'      => 1,
                  'keypress_key'          => 1,
                  'keypress_attempts'     => 1,
                  'keypress_timeout'      => 5,
                  'keypress_message_type' => 'AUDIO',
                  'keypress_message'      => null,

                  'whisper_enabled'       => 1,
                  'whisper_message'       => null,

                  'recording_enabled'     => 1, 
                  'transcription_enabled' => 1,
            ]);

            $configData = factory(PhoneNumberConfig::class)->make();

            $response = $this->json('PUT', route('update-phone-number-config', [
                  'company'           => $company->id,
                  'phoneNumberConfig' => $config->id
            ]), [
                  'name'                  => $configData->name,
                  'forward_to_number'     => $configData->forward_to_number,
                  'greeting_enabled'      => 0,
                  'greeting_message_type' => 'TEXT',
                  'greeting_message'      => $configData->greeting_message,

                  'keypress_enabled'      => 0,
                  'keypress_key'          => 2,
                  'keypress_attempts'     => 2,
                  'keypress_timeout'      => 10,
                  'keypress_message_type' => 'TEXT',
                  'keypress_message'      => $configData->keypress_message,

                  'whisper_enabled'       => 0,
                  'whisper_message'       => $configData->whisper_message,

                  'recording_enabled'     => 0, 
                  'transcription_enabled' => 0,
            ]);


            $response->assertJSON( [
                  'name'                  => $configData->name,
                  'forward_to_number'     => $configData->forward_to_number,
                  'greeting_enabled'      => false,
                  'greeting_message_type' => 'TEXT',
                  'greeting_message'      => $configData->greeting_message,

                  'keypress_enabled'      => false,
                  'keypress_key'          => 2,
                  'keypress_attempts'     => 2,
                  'keypress_timeout'      => 10,
                  'keypress_message_type' => 'TEXT',
                  'keypress_message'      => $configData->keypress_message,
                  'keypress_audio_clip_id'=> null,

                  'whisper_enabled'       => false,
                  'whisper_message'       => $configData->whisper_message,

                  'recording_enabled'     => false, 
                  'transcription_enabled' => false,

                  'updated_by'            => $this->user->id
            ]);

            $this->assertDatabaseHas('phone_number_configs', [
                  'name'                  => $configData->name,
                  'forward_to_number'     => $configData->forward_to_number,
                  'greeting_enabled'      => 0,
                  'greeting_message_type' => 'TEXT',
                  'greeting_message'      => $configData->greeting_message,

                  'keypress_enabled'      => 0,
                  'keypress_key'          => 2,
                  'keypress_attempts'     => 2,
                  'keypress_timeout'      => 10,
                  'keypress_message_type' => 'TEXT',
                  'keypress_message'      => $configData->keypress_message,

                  'whisper_enabled'       => 0,
                  'whisper_message'       => $configData->whisper_message,

                  'recording_enabled'     => 0, 
                  'transcription_enabled' => 0,

                  'updated_by'            => $this->user->id
            ]);
    }

    /**
     * Test deleting a phone number config
     * 
     * @group phone-number-configs
     */
    public function testDeletePhoneNumberConfig()
    {
            $company = $this->createCompany();
            $config  = factory(PhoneNumberConfig::class)->create([
                  'account_id' => $company->account_id,
                  'company_id' => $company->id,
                  'created_by' => $this->user->id
            ]);

            $response = $this->json('DELETE', route('delete-phone-number-config', [
                  'company' => $company->id,
                  'phoneNumberConfig' => $config->id
            ]));
            $response->assertStatus(200);
            $response->assertJSON([
                  'message' => 'Deleted'
            ]);

            $this->assertDatabaseMissing('phone_number_configs', [
                  'id'         => $config->id,
                  'deleted_at' => null
            ]);
    }

    /**
     * Test deleting config in use by number fails
     * 
     * @group phone-number-configs
     */
    public function testDeletePhoneNumberConfigInUseByNumberFails()
    {
            $company = $this->createCompany();
            $config  = factory(PhoneNumberConfig::class)->create([
                  'account_id' => $company->account_id,
                  'company_id' => $company->id,
                  'created_by' => $this->user->id
            ]);

            factory(PhoneNumber::class)->create([
                  'account_id' => $company->account_id,
                  'company_id' => $company->id,
                  'created_by' => $this->user->id,
                  'phone_number_config_id' => $config->id
            ]);

            $response = $this->json('DELETE', route('delete-phone-number-config', [
                  'company' => $company->id,
                  'phoneNumberConfig' => $config->id
            ]));

            $response->assertStatus(400);
            $response->assertJSONStructure([
                  'error'
            ]);

            $this->assertDatabaseHas('phone_number_configs', [
                  'id'         => $config->id,
                  'deleted_at' => null
            ]);
    }

}
