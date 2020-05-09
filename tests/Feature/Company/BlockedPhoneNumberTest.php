<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Company;
use App\Models\Company\BlockedPhoneNumber;
use App\Jobs\ExportResultsJob;
use Queue;
use DateTimeZone;

class BlockedPhoneNumberTest extends TestCase
{
    use \Tests\CreatesAccount;
    
    /**
     * Test listing blocked phone numbers
     * 
     * @group blocked-phone-numbers
     */
    public function testListBlockedPhoneNumbers()
    {
        $company        = $this->createCompany();
        $blockedNumbers = factory(BlockedPhoneNumber::class, mt_rand(1,4))->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('GET', route('list-company-blocked-phone-numbers', [
            'company' => $company->id
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            "results" => [

            ],
            "result_count" => count($blockedNumbers),
            "limit"       => 250,
            "page"        => 1,
            "total_pages" => 1,
            "next_page"   => null
        ]);
    }

    /**
     * Test listing blocked phone numbers with conditions
     * 
     * @group blocked-phone-numbers
     */
    public function testListBlockedPhoneNumbersWithConditions()
    {
        $company        = $this->createCompany();
        $blockedNumbers = factory(BlockedPhoneNumber::class, mt_rand(1,4))->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $firstNumber = $blockedNumbers->first();

        $response = $this->json('GET', route('list-company-blocked-phone-numbers', [
            'company' => $company->id,
            'conditions' => json_encode([
                [
                    'field'    => 'blocked_phone_numbers.name',
                    'operator' => 'EQUALS',
                    'inputs'   => [
                        $firstNumber->name
                    ]
                ]
            ])
        ]));
        $response->assertStatus(200);

        $response->assertJSON([
            "result_count" => 1,
            "limit"        => 250,
            "page"         => 1,
            "total_pages"  => 1,
            "next_page"    => null,
            "results" => [
                [
                    'id' => $firstNumber->id,
                    'name' => $firstNumber->name
                ]
            ],
        ]);
    }

    /**
     * Test creating blocked phone numbers
     * 
     * @group blocked-phone-numbers
     */
    public function testCreateBlockedPhoneNumbers()
    {
        $company       = $this->createCompany();
        $blockedNumber = factory(BlockedPhoneNumber::class)->make();

        $response = $this->json('POST', route('create-company-blocked-phone-number', [
            'company' => $company->id
        ]), [
            'numbers' => json_encode([
                [
                    'name' => 'Num 1',
                    'number' => '8887774444'
                ],
                [
                    'name' => 'Num 2',
                    'number' => '99990048854'
                ]
            ])
        ]);

        $response->assertJSONStructure([
            [
                'id',
                'number'
            ]
        ]);

        $this->assertDatabaseHas('blocked_phone_numbers', [
            'id' => $response[0]['id'],
            'number' => $response[0]['number'],
            'created_by' => $this->user->id
        ]);
    }

    /**
     * Test updating blocked phone numbers
     * 
     * @group blocked-phone-numbers
     */
    public function testUpdateBlockedPhoneNumbers()
    {
        $company       = $this->createCompany();
        $blockedNumber = factory(BlockedPhoneNumber::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $updatedNumber = factory(BlockedPhoneNumber::class)->make();

        $response = $this->json('PUT', route('update-company-blocked-phone-number', [
            'company'            => $company->id,
            'blockedPhoneNumber' => $blockedNumber->id
        ]), [
           'name' =>  $updatedNumber->name
        ]);

        $response->assertJSON([
            'id'     => $blockedNumber->id,
            'number' => $blockedNumber->number,
            'name'   => $updatedNumber->name,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id
        ]);

        $this->assertDatabaseHas('blocked_phone_numbers', [
            'id'         => $response['id'],
            'name'       => $response['name'],
            'number'     => $response['number'],
            'created_by' => $this->user->id
        ]);
    }

    /**
     * Test deleting blocked phone numbers
     * 
     * @group blocked-phone-numbers
     */
    public function testDeleteBlockedPhoneNumbers()
    {
        $company       = $this->createCompany();
        $blockedNumber = factory(BlockedPhoneNumber::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $updatedNumber = factory(BlockedPhoneNumber::class)->make();

        $response = $this->json('DELETE', route('update-company-blocked-phone-number', [
            'company'            => $company->id,
            'blockedPhoneNumber' => $blockedNumber->id
        ]));

        $response->assertJSON([
            'message' => 'deleted'
        ]);

        $this->assertDatabaseMissing('blocked_phone_numbers', [
            'id' => $blockedNumber->id,
            'deleted_at' => null
        ]);
    }

    /**
     * Test exporting blocked phone numbers
     * 
     * @group blocked-phone-numbers
     */
    public function testExportBlockedPhoneNumbers()
    {
        Queue::fake();
        
        $company = $this->createCompany();
        factory(BlockedPhoneNumber::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);
        
        $response = $this->json('GET', route('export-company-blocked-phone-numbers', [
            'company' => $company->id
        ]));
        
        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'queued'
        ]);

        Queue::assertPushed(ExportResultsJob::class, function ($job){
            return $job->model === BlockedPhoneNumber::class 
                && $job->user->id === $this->user->id
                && count($job->input);
        });
    }

    /**
     * Test exporting blocked phone numbers with conditions
     * 
     * @group blocked-phone-numbers
     */
    public function testExportBlockedPhoneNumbersWithConditions()
    {
        Queue::fake();
        
        $company = $this->createCompany();

        $blockedNumbers = factory(BlockedPhoneNumber::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $conditions = [
            [
                'field'    => 'blocked_phone_numbers.name',
                'operator' => 'EQUALS',
                'inputs'   => [
                    $blockedNumbers->first()->name
                ]
            ]
        ];

        $response = $this->json('GET', route('export-company-blocked-phone-numbers', [
            'company' => $company->id
        ]), [
            'conditions' => json_encode($conditions)
        ]);
        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'queued'
        ]);

        Queue::assertPushed(ExportResultsJob::class, function ($job) use($conditions){
            return $job->model === BlockedPhoneNumber::class 
                && $job->user->id === $this->user->id
                && json_decode($job->input['conditions'], true) == $conditions;
        });
    }

    /**
     * Test exporting blocked phone numbers with date ranges
     * 
     * @group blocked-phone-numbers
     */
    public function testExportBlockedPhoneNumbersWithDateRanges()
    {
       
        $twoDaysAgo  = now()->subDays(2);

        Queue::fake();
        
        $company = $this->createCompany();

        $oldBlockedNumber = factory(BlockedPhoneNumber::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'created_at' => $twoDaysAgo,
            'updated_at' => $twoDaysAgo
        ]);

        factory(BlockedPhoneNumber::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);
        
        $twoDaysAgo->setTimeZone(new DateTimeZone($this->user->timezone));
        $response = $this->json('GET', route('export-company-blocked-phone-numbers', [
            'company' => $company->id
        ]), [
            'start_date' => $twoDaysAgo->format('Y-m-d'),
            'end_date'   => $twoDaysAgo->format('Y-m-d')
        ]);

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'queued'
        ]);

        Queue::assertPushed(ExportResultsJob::class, function ($job) use($twoDaysAgo){
            return $job->model === BlockedPhoneNumber::class 
                && $job->user->id === $this->user->id
                && $job->input['start_date'] == $twoDaysAgo->format('Y-m-d')
                && $job->input['end_date'] == $twoDaysAgo->format('Y-m-d');
        });
    }
}
