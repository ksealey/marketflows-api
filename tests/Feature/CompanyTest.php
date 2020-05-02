<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use \App\Models\BlockedPhoneNumber;
use \App\Models\BlockedPhoneNumber\BlockedCall;
use \App\Models\Company;
use \App\Models\Company\AudioClip;
use \App\Models\Company\PhoneNumberConfig;
use \App\Models\Company\PhoneNumberPool;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\Call;
use \App\Models\Company\Report;
use \App\Models\Company\ReportAutomation;
use \App\Helpers\PhoneNumberManager;
use Storage;

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
     * @group companies-
     */
    public function testDeleteCompany()
    {
        Storage::fake();

        $data           = $this->createCompanies();
        $company        = $data['company'];
        $audioClip      = $data['audio_clip'];
        $phoneNumber    = $data['phone_number'];
        $report         = $data['report'];

        $this->mock(PhoneNumberManager::class, function ($mock) use($data){
            $mock->shouldReceive('release')
                 ->times(count($data['phone_number_pool_numbers']));
        });

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
        $this->assertDatabaseMissing('companies', [
            'id' => $company->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('audio_clips', [
            'company_id' => $company->id,
            'deleted_at' => null
        ]);
        Storage::assertMissing($audioClip->path);

        $this->assertDatabaseMissing('phone_number_configs', [
            'company_id' => $company->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('phone_numbers', [
            'company_id' => $company->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('phone_number_pools', [
            'company_id' => $company->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('calls', [
            'company_id' => $company->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('blocked_phone_numbers', [
            'company_id' => $company->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('blocked_calls', [
            'phone_number_id' => $phoneNumber->id,
            'deleted_at'      => null
        ]);

        $this->assertDatabaseMissing('reports', [
            'company_id' => $company->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('report_automations', [
            'report_id'  => $report->id
        ]);
    }
}
