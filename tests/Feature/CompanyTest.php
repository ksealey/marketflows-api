<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Company;
use App\Models\Company\ScheduledExport;
use App\Models\Company\Report;
use App\Models\Company\Webhook;
use App\Models\Company\Contact;
use App\Models\Company\PhoneNumberConfig;
use App\Models\Company\KeywordTrackingPool;
use App\Models\Company\Call;
use App\Models\Company\CallRecording;
use App\Models\Company\AudioClip;
use App\Models\Company\PhoneNumber;
use App\Services\PhoneNumberService;
use App\Services\ExportService;
use Storage;
use Queue;
use DateTime;
use DateTimeZone;

class CompanyTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test creating a company
     * 
     * @group companies
     */
    public function testCreateCompany()
    {
        $company = factory(Company::class)->make();
        $response = $this->json('POST', route('create-company'), [
            'name'     => $company->name,
            'country'  => $company->country,
            'industry' => $company->industry,
            'ga_id'    => $company->ga_id,
            'tts_language'  => $company->tts_language,
            'tts_voice'     => $company->tts_voice
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'name'     => $company->name,
            'country'  => $company->country,
            'industry' => $company->industry,
            'ga_id'    => $company->ga_id,
            'tts_language'  => $company->tts_language,
            'tts_voice'     => $company->tts_voice,
            'created_by' => $this->user->id,
            'updated_by' => null
        ]);

        $this->assertDatabaseHas('companies', [
            'id'       => $response['id'],
            'name'     => $company->name,
            'country'  => $company->country,
            'industry' => $company->industry,
            'ga_id'    => $company->ga_id,
            'tts_language'  => $company->tts_language,
            'tts_voice'     => $company->tts_voice,
            'created_by' => $this->user->id,
            'updated_by' => null
        ]);
    }

    /**
     * Test viewing a company
     * 
     * @group companies
     */
    public function testReadCompany()
    {
        $company = factory(Company::class)->create([
            'account_id' => $this->user->account_id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('GET', route('read-company', [
            'company' => $company->id
        ]));
       
        $response->assertStatus(200);
        $response->assertJSON([
            'id'       => $company->id,
            'name'     => $company->name,
            'country'  => $company->country,
            'industry' => $company->industry,
            'created_by' => $this->user->id,
            'updated_by' => null
        ]);
    }

    /**
     * Test list companies
     * 
     * @group companies
     */
    public function testListCompanies()
    {
        $companies = factory(Company::class, 5)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->json('GET', route('list-companies'), [
            'date_type' => 'ALL_TIME'
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            "result_count" => 5,
            "limit" => 250,
            "page" => 1,
            "total_pages" =>  1,
            "next_page" => null,
        ]);
        $response->assertJSONStructure([
            "results" => [
                [
                    'id',
                    'name',
                    'country',
                    'industry'
                ]
            ]
        ]);
    }

    /**
     * Test listing companies with conditions
     * 
     * @group companies
     */
    public function testListCompaniesWithConditions()
    {
        $companies = factory(Company::class, 5)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id,
        ]);

        $firstCompany = $companies->first();

        $conditions = [
            [
                [
                    'field'    => 'companies.name',
                    'operator' => 'EQUALS',
                    'inputs'   => [
                        $firstCompany->name
                    ]
                ]
            ]
        ];

        $response = $this->json('GET', route('list-companies'), [
            'conditions' => json_encode($conditions),
            'date_type'  => 'ALL_TIME'
        ]);

        $response->assertStatus(200);

        $response->assertJSON([
            "result_count" => 1,
            "limit" => 250,
            "page" => 1,
            "total_pages" =>  1,
            "next_page" => null,
            "results" => [
                [
                    'id' => $firstCompany->id,
                    'name' => $firstCompany->name,
                    'country' => $firstCompany->country,
                    'industry' => $firstCompany->industry
                ]
            ]
        ]);
    }

    /**
     * Test listing companies with date ranges
     * 
     * @group companies
     */
    public function testListCompaniesWithDateRanges()
    {
        $twoDaysAgo  = now()->subDays(2);
        $oldCompany  = factory(Company::class)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id,
            'created_at' => $twoDaysAgo,
            'updated_at' => $twoDaysAgo
        ]);

        $companies  = factory(Company::class, 2)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id,
        ]);
        
        $twoDaysAgo->setTimeZone(new DateTimeZone($this->user->timezone));
        $response = $this->json('GET', route('list-companies'), [
            'start_date' => $twoDaysAgo->format('Y-m-d'),
            'end_date'   => $twoDaysAgo->format('Y-m-d'),
            'date_type' => 'CUSTOM'
        ]);

        $response->assertStatus(200);

        $response->assertJSON([
            "result_count" => 1,
            "limit" => 250,
            "page" => 1,
            "total_pages" =>  1,
            "next_page" => null,
            "results" => [
                [
                    'id' => $oldCompany->id,
                    'name' => $oldCompany->name,
                    'country' => $oldCompany->country,
                    'industry' => $oldCompany->industry
                ]
            ]
        ]);
    }

    /**
     * Test exportng companies
     * 
     * @group companies
     */
    public function testExportCompanies()
    {
        $companies  = factory(Company::class, 2)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id,
        ]);

        $exportData  = bin2hex(random_bytes(200));
        $this->mock(ExportService::class, function($mock) use($exportData){
            $mock->shouldReceive('exportAsOutput')
                 ->once()
                 ->andReturn($exportData);
        });
        $response = $this->json('GET', route('export-companies'));
        $response->assertStatus(200);
        $response->assertSee($exportData);
    }

    /**
     * Test exporting companies with conditions
     * 
     * @group companies
     */
    public function testExportCompaniesWithConditions()
    {
        $exportData  = bin2hex(random_bytes(200));
        $this->mock(ExportService::class, function($mock) use($exportData){
            $mock->shouldReceive('exportAsOutput')
                 ->once()
                 ->andReturn($exportData);
        });
        
        $companies  = factory(Company::class, 2)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id,
        ]);

        $conditions = [
            [
                [
                    'field'    => 'companies.name',
                    'operator' => 'EQUALS',
                    'inputs'   => [
                        $companies->first()->name
                    ]
                ]
            ]
        ];

        $response = $this->json('GET', route('export-companies'), [
            'conditions' => json_encode($conditions)
        ]);
        $response->assertStatus(200);
        $response->assertSee($exportData);
    }

    /**
     * Test exporting companies with date ranges
     * 
     * @group companies
     */
    public function testExportCompaniesWithDateRanges()
    {
        $exportData  = bin2hex(random_bytes(200));
        $this->mock(ExportService::class, function($mock) use($exportData){
            $mock->shouldReceive('exportAsOutput')
                 ->once()
                 ->andReturn($exportData);
        });

        $twoDaysAgo  = now()->subDays(2);
        $oldCompany  = factory(Company::class)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id,
            'created_at' => $twoDaysAgo,
            'updated_at' => $twoDaysAgo
        ]);

        $companies  = factory(Company::class, 2)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id,
        ]);
        
        $twoDaysAgo->setTimeZone(new DateTimeZone($this->user->timezone));
        $response = $this->json('GET', route('export-companies'), [
            'start_date' => $twoDaysAgo->format('Y-m-d'),
            'end_date'   => $twoDaysAgo->format('Y-m-d')
        ]);

        $response->assertStatus(200);
        $response->assertSee($exportData);
    }

    /**
     * Test updating a company
     * 
     * @group companies
     */
    public function testUpdateCompany()
    {
        $company = factory(Company::class)->create([
            'account_id' => $this->user->account_id,
            'created_by' => $this->user->id
        ]);

        $updateData = factory(Company::class)->make();

        $response = $this->json('PUT', route('update-company', [
            'company' => $company->id
        ]), [
            'name'          => $updateData->name,
            'country'       => $updateData->country,
            'industry'      => $updateData->industry,
            'ga_id'         => $updateData->ga_id,
            'tts_language'  => $updateData->tts_language,
            'tts_voice'     => $updateData->tts_voice,
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'id'       => $company->id,
            'name'     => $updateData->name,
            'country'  => $updateData->country,
            'industry' => $updateData->industry,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id
        ]);

        $this->assertDatabaseHas('companies', [
            'id'       => $response['id'],
            'name'     => $updateData->name,
            'country'  => $updateData->country,
            'industry' => $updateData->industry,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id
        ]);
    }

    /**
     * Test deleting a company
     * 
     * @group companies
     */
    public function testDeleteCompany()
    {
        Storage::fake();

        $company = $this->createCompany();

        $webhook = factory(Webhook::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $audioClip = factory(AudioClip::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'path'       => '/accounts/' . $company->account_id . '/companies/' . $company->id .'/file.mp3',
            'created_by' => $this->user->id
        ]);
        Storage::put($audioClip->path, str_random(40));

        $config = factory(PhoneNumberConfig::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'greeting_audio_clip_id' => $audioClip->id,
        ]);

        $keywordTrackingPool = factory(KeywordTrackingPool::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'phone_number_config_id' => $config->id
        ]);

        $poolNumbers = factory(PhoneNumber::class, 5)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'keyword_tracking_pool_id' => $keywordTrackingPool->id,
            'phone_number_config_id' => $config->id
        ]);

        $detachedNumbers = factory(PhoneNumber::class, 5)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'phone_number_config_id' => $config->id
        ]);

        foreach($detachedNumbers as $phoneNumber){
            $contacts = factory(Contact::class, 2)->create([
                'account_id' => $company->account_id,
                'company_id' => $company->id,
            ])->each(function($contact) use($company, $phoneNumber){
                $call = factory(Call::class)->create([
                    'account_id'    => $company->account_id,
                    'company_id'    => $company->id,
                    'contact_id'    => $contact->id,
                    'phone_number_id'=> $phoneNumber->id,
                    'phone_number_name' => $phoneNumber->name
                ]);

                factory(CallRecording::class)->create([
                    'account_id'    => $company->account_id,
                    'company_id'    => $company->id,
                    'call_id'       => $call->id
                ]);

                Storage::put('/accounts/' . $company->account_id . '/companies/' . $company->id . '/' . $call->id . '.mp3', 'content');
            });
        }

        $report = factory(Report::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
        ]);

        $scheduledExport = factory(ScheduledExport::class)->create([
            'company_id' => $company->id,
            'report_id'  => $report->id
        ]);
    
        //    
        //  Perform delete
        //
        $this->mock(PhoneNumberService::class, function($mock) use($company){
            $mock->shouldReceive('releaseNumber')
                 ->times(PhoneNumber::where('company_id', $company->id)->count());
        });
        $response = $this->json('DELETE', route('delete-company', [
            'company' => $company->id
        ]));
        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'Deleted'
        ]);

        //
        //  Make sure the resources were removed
        //
        $this->assertDatabaseMissing('companies', [
            'id'         => $company->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('webhooks', [
            'id'         => $webhook->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('audio_clips', [
            'id'         => $audioClip->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('phone_number_configs', [
            'id'         => $config->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('keyword_tracking_pools', [
            'id'         => $keywordTrackingPool->id,
            'deleted_at' => null
        ]);

        foreach($poolNumbers as $phoneNumber){
            $this->assertDatabaseMissing('phone_numbers', [
                'id'         => $phoneNumber->id,
                'deleted_at' => null
            ]);
        }

        foreach($detachedNumbers as $phoneNumber){
            $this->assertDatabaseMissing('phone_numbers', [
                'id'         => $phoneNumber->id,
                'deleted_at' => null
            ]);
        }

        $this->assertDatabaseMissing('contacts', [
            'company_id' => $company->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('calls', [
            'company_id' => $company->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('call_recordings', [
            'company_id' => $company->id,
            'deleted_at' => null
        ]);

        Storage::assertMissing('/accounts/' . $company->account_id . '/companies/' . $company->id);
    }
}