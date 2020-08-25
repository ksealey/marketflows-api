<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use \App\Models\Company\BlockedPhoneNumber;
use \App\Models\Company\BlockedPhoneNumber\BlockedCall;
use \App\Models\Company;
use \App\Models\Company\AudioClip;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\Call;
use \App\Models\Company\CallRecording;
use \App\Helpers\PhoneNumberManager;
use \App\Jobs\BatchDeleteAudioJob;
use \App\Jobs\BatchDeletePhoneNumbersJob;
use \App\Jobs\BatchDeleteCallRecordingsJob;
use \App\Jobs\ExportResultsJob;
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
                'field'    => 'companies.name',
                'operator' => 'EQUALS',
                'inputs'   => [
                    $firstCompany->name
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
        Queue::fake();
        
        $companies  = factory(Company::class, 2)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->json('GET', route('export-companies'));
        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'queued'
        ]);

        Queue::assertPushed(ExportResultsJob::class, function ($job){
            return $job->model === Company::class 
                && $job->user->id === $this->user->id
                && count($job->input);
        });
    }

    /**
     * Test exporting companies with conditions
     * 
     * @group companies
     */
    public function testExportCompaniesWithConditions()
    {
        Queue::fake();
        
        $companies  = factory(Company::class, 2)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id,
        ]);

        $conditions = [
            [
                'field'    => 'companies.name',
                'operator' => 'EQUALS',
                'inputs'   => [
                    $companies->first()->name
                ]
            ]
        ];

        $response = $this->json('GET', route('export-companies'), [
            'conditions' => json_encode($conditions)
        ]);
        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'queued'
        ]);

        Queue::assertPushed(ExportResultsJob::class, function ($job) use($conditions){
            return $job->model === Company::class 
                && $job->user->id === $this->user->id
                && json_decode($job->input['conditions'], true) == $conditions;
        });
    }

    /**
     * Test exporting companies with date ranges
     * 
     * @group companies
     */
    public function testExportCompaniesWithDateRanges()
    {
        Queue::fake();

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

        $response->assertJSON([
            'message' => 'queued'
        ]);

        Queue::assertPushed(ExportResultsJob::class, function ($job) use($twoDaysAgo){
            return $job->model === Company::class 
                && $job->user->id === $this->user->id
                && $job->input['start_date'] == $twoDaysAgo->format('Y-m-d')
                && $job->input['end_date'] == $twoDaysAgo->format('Y-m-d');
        });
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
        Queue::fake();
        Storage::fake();

        $data           = $this->createCompanies();
        $company        = $data['company'];
        $audioClip      = $data['audio_clip'];
        $phoneNumber    = $data['phone_number'];

        //    
        //  Perform delete
        //
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

        //  Companies
        $this->assertDatabaseHas('companies', [
            'id'         => $company->id,
            'deleted_by' => $this->user->id
        ]);
        $this->assertDatabaseMissing('companies', [
            'id' => $company->id,
            'deleted_at' => null
        ]);

        //  Phone number configs
        $this->assertDatabaseHas('phone_number_configs', [
            'company_id' => $company->id,
            'deleted_by' => $this->user->id
        ]);
        $this->assertDatabaseMissing('phone_number_configs', [
            'company_id' => $company->id,
            'deleted_at' => null
        ]);

        //  Calls
        $this->assertDatabaseHas('calls', [
            'company_id' => $company->id,
            'deleted_by' => $this->user->id
        ]);
        $this->assertDatabaseMissing('calls', [
            'company_id' => $company->id,
            'deleted_at' => null
        ]);

        //  Blocked phone numbers (Company)
        $this->assertDatabaseHas('blocked_phone_numbers', [
            'company_id' => $company->id,
            'deleted_by' => $this->user->id
        ]);
        $this->assertDatabaseMissing('blocked_phone_numbers', [
            'company_id' => $company->id,
            'deleted_at' => null
        ]);

         //  Blocked phone numbers (Account)
         $this->assertDatabaseHas('blocked_phone_numbers', [
            'company_id' => $company->id,
            'deleted_by' => $this->user->id
        ]);
        $this->assertDatabaseMissing('blocked_phone_numbers', [
            'company_id' => $company->id,
            'deleted_at' => null
        ]);

        //  Blocked Calls (Company)
        $this->assertDatabaseMissing('blocked_calls', [
            'phone_number_id' => $phoneNumber->id,
            'deleted_at'      => null
        ]);

        //  Make sure the batch jobs to delete remote resources were dispatched
        Queue::assertPushed(BatchDeleteAudioJob::class, function ($job) use ($company) {
            return $job->company->id === $company->id && $job->user->id === $this->user->id;
        });
        Queue::assertPushed(BatchDeletePhoneNumbersJob::class, function ($job) use ($company) {
            return $job->company->id === $company->id && $job->user->id === $this->user->id;
        });
        Queue::assertPushed(BatchDeleteCallRecordingsJob::class, function ($job) use ($company) {
            return $job->company->id === $company->id && $job->user->id === $this->user->id;
        });
    }

    /**
     * Test that the back jobs for companies work as eexpected
     * 
     * @group companies
     */
    public function testDeleteCompaniesBatchJobsWork()
    {
        $data           = $this->createCompanies();
        $company        = $data['company'];
        $audioClip      = $data['audio_clip'];
        $phoneNumber    = $data['phone_number'];

        //
        //  Setup mock
        //
        $this->mock(PhoneNumberManager::class, function ($mock) use($phoneNumber){
            $mock->shouldReceive('releaseNumber')
                 ->once()
                 ->with(PhoneNumber::class);
        });


        //
        //  Make sure the files are there originally
        //
        Storage::assertExists($audioClip->path);
        CallRecording::whereIn('call_id', function($q) use($company){
            $q->select('id')
              ->from('calls')
              ->where('company_id', $company->id); 
        })
        ->get()
        ->each(function($callRecording){
            Storage::assertExists($callRecording->path);
        });

        //    
        //  Perform delete
        //
        $response = $this->json('DELETE', route('delete-company', [
            'company' => $company->id
        ]));
        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'Deleted'
        ]);

        //
        //  Make sure audio clip files are deleted
        //
        AudioClip::withTrashed()
                 ->where('company_id', $company->id)
                 ->get()
                 ->each(function($audioClip){
                    Storage::assertMissing($audioClip->path);
                });

        //
        //  Make sure the call recording files are deleted
        //
        CallRecording::withTrashed()
                     ->whereIn('call_id', function($q) use($company){
                        $q->select('id')
                            ->from('calls')
                            ->where('company_id', $company->id); 
                    })
                    ->get()
                    ->each(function($callRecording){
                        Storage::assertMissing($callRecording->path);
                    });


        
    }
}