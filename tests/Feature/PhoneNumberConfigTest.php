<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Company;
use App\Models\Company\PhoneNumberConfig;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumberPool;
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
            $configData = factory(PhoneNumberConfig::class)->make();
            $postData   = [
                  'name'                  => $configData->name,
                  'forward_to_number'     => $configData->forward_to_number,
                  'recording_enabled'     => 1,
                  'keypress_enabled'      => 1,
                  'keypress_key'          => 0,
                  'keypress_attempts'     => 1,
                  'keypress_timeout'      => 5
            ];

            $response = $this->json('POST', route('create-phone-number-config', [
                  'company' => $company->id
            ]), $postData);

            $response->assertStatus(201);
            $response->assertJSON($postData);

            $this->assertDatabaseHas('phone_number_configs', [
                  'id'                    => $response['id'],
                  'forward_to_number'     => $configData->forward_to_number,
                  'recording_enabled'     => 1,
                  'keypress_enabled'      => 1,
                  'keypress_key'          => 0,
                  'keypress_attempts'     => 1,
                  'keypress_timeout'      => 5
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
                  'company_id' => $company->id,
                  'created_by' => $this->user->id
            ]);

            $response = $this->json('GET', route('read-phone-number-config', [
                  'company' => $company->id,
                  'phoneNumberConfig' => $config->id
            ]));

            $response->assertJSON([
                  'id' => $config->id,
                  'name' => $config->name,
                  'company_id' => $company->id,
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
                  'company_id' => $company->id,
                  'created_by' => $this->user->id
            ]);

            $response = $this->json('GET', route('list-phone-number-configs', [
                  'company' => $company->id
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
                             'name'
                       ]
                 ]
            ]);
    }

    /**
     * Test updating phone number config
     * 
     * @group phone-number-configs
     */
    public function testUpdatePhoneNumberConfig()
    {
            $company = $this->createCompany();
            $config  = factory(PhoneNumberConfig::class)->create([
                  'company_id' => $company->id,
                  'created_by' => $this->user->id,
                  'recording_enabled'     => 1,
                  'keypress_enabled'      => 1,
                  'keypress_key'          => 0,
                  'keypress_attempts'     => 1,
                  'keypress_timeout'      => 5
            ]);

            $configData = factory(PhoneNumberConfig::class)->make();

            $response = $this->json('PUT', route('update-phone-number-config', [
                  'company'           => $company->id,
                  'phoneNumberConfig' => $config->id
            ]), [
                  'name'                 => $configData->name,
                  'forward_to_number'     => $configData->forward_to_number,
                  'recording_enabled'     => 0,
                  'keypress_enabled'      => 0,
                  'keypress_key'          => '',
                  'keypress_attempts'     => '',
                  'keypress_timeout'      => ''
            ]);


            $response->assertJSON( [
                  'name'              => $configData->name,
                  'forward_to_number' => $configData->forward_to_number,
                  'recording_enabled' => false,
                  'keypress_enabled'  => false,
                  'keypress_key'      => null,
                  'keypress_attempts' => null,
                  'keypress_timeout'  => null
            ]);

            $this->assertDatabaseHas('phone_number_configs', [
                  'name'              => $configData->name,
                  'forward_to_number' => $configData->forward_to_number,
                  'recording_enabled' => false,
                  'keypress_enabled'  => false,
                  'keypress_key'      => null,
                  'keypress_attempts' => null,
                  'keypress_timeout'  => null
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
                  'company_id' => $company->id,
                  'created_by' => $this->user->id,
                  'recording_enabled'     => 1,
                  'keypress_enabled'      => 1,
                  'keypress_key'          => 0,
                  'keypress_attempts'     => 1,
                  'keypress_timeout'      => 5
            ]);

            $response = $this->json('DELETE', route('delete-phone-number-config', [
                  'company' => $company->id,
                  'phoneNumberConfig' => $config->id
            ]));
            $response->assertStatus(200);
            $response->assertJSON([
                  'message' => 'deleted'
            ]);

            $this->assertDatabaseMissing('phone_number_configs', [
                  'id' => $config->id,
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
                  'company_id' => $company->id,
                  'created_by' => $this->user->id,
                  'recording_enabled'     => 1,
                  'keypress_enabled'      => 1,
                  'keypress_key'          => 0,
                  'keypress_attempts'     => 1,
                  'keypress_timeout'      => 5
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

    /**
     * Test deleting config in use by number pool fails
     * 
     * @group phone-number-configs
     */
    public function testDeletePhoneNumberConfigInUseByNumberPoolFails()
    {
            $company = $this->createCompany();
            $config  = factory(PhoneNumberConfig::class)->create([
                  'company_id' => $company->id,
                  'created_by' => $this->user->id,
                  'recording_enabled'     => 1,
                  'keypress_enabled'      => 1,
                  'keypress_key'          => 0,
                  'keypress_attempts'     => 1,
                  'keypress_timeout'      => 5
            ]);

            factory(PhoneNumberPool::class)->create([
                  'account_id' => $company->account_id,
                  'company_id' => $company->id,
                  'created_by' => $this->user->id,
                  'phone_number_config_id' => $config->id
            ]);

            $response = $this->json('DELETE', route('delete-phone-number-config', [
                  'company'           => $company->id,
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
