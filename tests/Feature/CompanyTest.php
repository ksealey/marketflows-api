<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use \App\Models\BlockedPhoneNumber;
use \App\Models\BlockedPhoneNumber\BlockedCall;
use \App\Models\Company;
use \App\Models\Company\AudioClip;
use \App\Models\Company\PhoneNumberPool;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\Call;
use \App\Models\Company\CallRecording;
use \App\Models\Company\Report;
use \App\Models\Company\ReportAutomation;
use \App\Helpers\PhoneNumberManager;
use \App\Jobs\BatchDeleteAudioJob;
use \App\Jobs\BatchHandleDeletedPhoneNumbersJob;
use \App\Jobs\BatchDeleteCallRecordingsJob;
use Storage;
use Queue;

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
            'industry' => $company->industry
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'name'     => $company->name,
            'country'  => $company->country,
            'industry' => $company->industry,
            'created_by' => $this->user->id,
            'updated_by' => null
        ]);

        $this->assertDatabaseHas('companies', [
            'id'       => $response['id'],
            'name'     => $company->name,
            'country'  => $company->country,
            'industry' => $company->industry,
            'created_by' => $this->user->id,
            'updated_by' => null
        ]);

        $this->assertDatabaseMissing('user_companies', [
            'id' => $response['id']
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
        $companies = factory(Company::class, 30)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->json('GET', route('list-companies'));
        $response->assertStatus(200);
        $response->assertJSON([
            "result_count" => 30,
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
            'name'     => $updateData->name,
            'country'  => $updateData->country,
            'industry' => $updateData->industry
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

        $this->assertDatabaseMissing('user_companies', [
            'id' => $response['id']
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

        $data           = $this->createCompanies();
        $company        = $data['company'];
        $audioClip      = $data['audio_clip'];
        $phoneNumber    = $data['phone_number'];
        $report         = $data['report'];

        //    
        //  Perform delete
        //
        $response = $this->json('DELETE', route('delete-company', [
            'company' => $company->id
        ]));
        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'deleted'
        ]);

        //
        //  Make sure the reources were removed
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

        //  Audio clips
        $this->assertDatabaseHas('audio_clips', [
            'company_id' => $company->id,
            'deleted_by' => $this->user->id
        ]);
        $this->assertDatabaseMissing('audio_clips', [
            'company_id' => $company->id,
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

        //  Phone numbers
        $this->assertDatabaseHas('phone_numbers', [
            'company_id' => $company->id,
            'deleted_by' => $this->user->id
        ]);
        $this->assertDatabaseMissing('phone_numbers', [
            'company_id' => $company->id,
            'deleted_at' => null
        ]);

        //  Phone number pools
        $this->assertDatabaseHas('phone_number_pools', [
            'company_id' => $company->id,
            'deleted_by' => $this->user->id
        ]);
        $this->assertDatabaseMissing('phone_number_pools', [
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

        //  Call recordings
        $callRecordingCount = CallRecording::whereIn('call_id', function($q) use($company){
            $q->select('id')
              ->from('calls')
              ->where('company_id', $company->id);
        })->count(); 
        $this->assertEquals($callRecordingCount, 0);

        //  Blocked phone numbers
        $this->assertDatabaseHas('blocked_phone_numbers', [
            'company_id' => $company->id,
            'deleted_by' => $this->user->id
        ]);
        $this->assertDatabaseMissing('blocked_phone_numbers', [
            'company_id' => $company->id,
            'deleted_at' => null
        ]);

        //  Blocked Calls
        $this->assertDatabaseMissing('blocked_calls', [
            'phone_number_id' => $phoneNumber->id,
            'deleted_at'      => null
        ]);

        //  Reports
        $this->assertDatabaseHas('reports', [
            'company_id' => $company->id,
            'deleted_by' => $this->user->id
        ]);
        $this->assertDatabaseMissing('reports', [
            'company_id' => $company->id,
            'deleted_at' => null
        ]);

        //  Report Automations
        $this->assertDatabaseMissing('report_automations', [
            'report_id'  => $report->id
        ]);

        //  Make sure the batch jobs to delete remote resources were dispatched
        Queue::assertPushed(BatchDeleteAudioJob::class, 1, function ($job) use ($company) {
            return $company->id === $job->companyId;
        });
        Queue::assertPushed(BatchHandleDeletedPhoneNumbersJob::class, 1, function ($job) use ($company) {
            return $company->id === $job->companyId;
        });
        Queue::assertPushed(BatchDeleteCallRecordingsJob::class, 1, function ($job) use ($company) {
            return $company->id === $job->companyId;
        });
    }
}