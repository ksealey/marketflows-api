<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\BlockedPhoneNumber;
use App\Models\BlockedCall;
use App\Models\Company\PhoneNumberConfig;
use App\Models\Company\PhoneNumber;
use App\Services\ExportService;
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
        $blockedNumbers = factory(BlockedPhoneNumber::class, mt_rand(1,4))->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('GET', route('list-blocked-phone-numbers'));

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
     * Test listing blocked phone numbers with all conditions
     * 
     * @group blocked-phone-numbers
     */
    public function testListBlockedPhoneNumbersWithAllConditions()
    {
        $blockedNumbers = factory(BlockedPhoneNumber::class, mt_rand(1,4))->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id
        ]);

        $conditions = $this->createConditions(BlockedPhoneNumber::accessibleFields(), true);
        $response = $this->json('GET', route('list-blocked-phone-numbers', [
            'conditions' => $conditions
        ]));

        $response->assertJSON([
            "limit"        => 250,
            "next_page"    => null,
            "results"      => [],
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test listing blocked phone numbers with conditions
     * 
     * @group blocked-phone-numbers
     */
    public function testListBlockedPhoneNumbersWithCondition()
    {
        $blockedNumbers = factory(BlockedPhoneNumber::class, mt_rand(1,4))->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id
        ]);

        $firstNumber = $blockedNumbers->first();

        $response = $this->json('GET', route('list-blocked-phone-numbers', [
            'conditions' => json_encode([
                [
                    [
                        'field'    => 'blocked_phone_numbers.name',
                        'operator' => 'EQUALS',
                        'inputs'   => [
                            $firstNumber->name
                        ]
                    ]
                ]
            ]),
        ]));
        
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

        $response->assertStatus(200);
    }

    /**
     * Test creating blocked phone numbers
     * 
     * @group blocked-phone-numbers
     */
    public function testCreateBlockedPhoneNumbers()
    {
        $blockedNumber = factory(BlockedPhoneNumber::class)->make();

        $response = $this->json('POST', route('create-blocked-phone-number'), [
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
        $blockedNumber = factory(BlockedPhoneNumber::class)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id
        ]);

        $updatedNumber = factory(BlockedPhoneNumber::class)->make();

        $response = $this->json('PUT', route('update-blocked-phone-number', [
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
        $blockedNumber = factory(BlockedPhoneNumber::class)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id
        ]);

        $updatedNumber = factory(BlockedPhoneNumber::class)->make();

        $response = $this->json('DELETE', route('update-blocked-phone-number', [
            'blockedPhoneNumber' => $blockedNumber->id
        ]));

        $response->assertJSON([
            'message' => 'Deleted'
        ]);

        $this->assertDatabaseMissing('blocked_phone_numbers', [
            'id'         => $blockedNumber->id,
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
        factory(BlockedPhoneNumber::class)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id
        ]);

        $exportData  = bin2hex(random_bytes(200));
        $this->mock(ExportService::class, function($mock) use($exportData){
            $mock->shouldReceive('exportAsOutput')
                 ->once()
                 ->andReturn($exportData);
        });
        
        $response = $this->json('GET', route('export-blocked-phone-numbers'));
        
        $response->assertStatus(200);
        $response->assertSee($exportData);
            
    }

    /**
     * Test exporting blocked phone numbers with conditions
     * 
     * @group blocked-phone-numbers
     */
    public function testExportBlockedPhoneNumbersWithConditions()
    {
        $exportData  = bin2hex(random_bytes(200));
        $this->mock(ExportService::class, function($mock) use($exportData){
            $mock->shouldReceive('exportAsOutput')
                 ->once()
                 ->andReturn($exportData);
        });

        $blockedNumbers = factory(BlockedPhoneNumber::class)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id
        ]);
        $conditions     = [
            [
                [
                    'field'    => 'blocked_phone_numbers.name',
                    'operator' => 'EQUALS',
                    'inputs'   => [
                        $blockedNumbers->first()->name
                    ]
                ]
            ]
        ];

        $response = $this->json('GET', route('export-blocked-phone-numbers'), [
            'conditions' => json_encode($conditions)
        ]);

        $response->assertStatus(200);
        $response->assertSee($exportData);
    }

    /**
     * Test exporting blocked phone numbers with date ranges
     * 
     * @group blocked-phone-numbers
     */
    public function testExportBlockedPhoneNumbersWithDateRanges()
    {
        $exportData  = bin2hex(random_bytes(200));
        $this->mock(ExportService::class, function($mock) use($exportData){
            $mock->shouldReceive('exportAsOutput')
                 ->once()
                 ->andReturn($exportData);
        });

        $twoDaysAgo  = now()->subDays(2);
        $oldBlockedNumber = factory(BlockedPhoneNumber::class)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id,
            'created_at' => $twoDaysAgo,
            'updated_at' => $twoDaysAgo
        ]);

        factory(BlockedPhoneNumber::class)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id
        ]);
        
        $twoDaysAgo->setTimeZone(new DateTimeZone($this->user->timezone));
        $response = $this->json('GET', route('export-blocked-phone-numbers'), [
            'start_date' => $twoDaysAgo->format('Y-m-d'),
            'end_date'   => $twoDaysAgo->format('Y-m-d')
        ]);

        $response->assertStatus(200);
        $response->assertSee($exportData);
    }

    /**
     * Test fetching blocked calls for a blocked phone number
     * 
     * @group blocked-phone-numbers
     */
    public function testFetchBlockedCalls()
    {
        $company = $this->createCompany();

        $config = factory(PhoneNumberConfig::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $myNumber = factory(PhoneNumber::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'phone_number_config_id' => $config->id
        ]);

        $blockedNumber = factory(BlockedPhoneNumber::class)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id
        ]);

        $blockedCalls = factory(BlockedCall::class, 5)->create([
            'account_id'              => $this->account->id,
            'blocked_phone_number_id' => $blockedNumber->id,
            'phone_number_id'         => $myNumber->id
        ]);

        $response = $this->json('GET', route('list-blocked-calls', [
            'blockedPhoneNumber' => $blockedNumber->id
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            "result_count" => 5,
            "limit"        => 250,
            "page"         => 1,
            "total_pages"  => 1,
            "next_page"    => null,
            'results'      => []
        ]);
    }
}
