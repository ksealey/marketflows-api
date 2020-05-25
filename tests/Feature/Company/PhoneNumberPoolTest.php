<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Twilio\Rest\Client as Twilio;
use \App\Models\Account;
use \App\Models\Company\PhoneNumberConfig;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\PhoneNumberPool;
use \App\Models\Company\Call;
use \App\Models\BankedPhoneNumber;
use \App\Helpers\PhoneNumberManager;
use \App\Jobs\ExportResultsJob;
use DateTimeZone;
use Queue;

class PhoneNumberPoolTest extends TestCase
{
    use \Tests\CreatesAccount, RefreshDatabase;

    /*
    |-------------
    | List/Read
    |------------- 
    |
    | 
    */

    /**
     * Test listing phone numbers pools
     * 
     * @group phone-number-pools
     */
    public function testListingPhoneNumberPools()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config);

        $response = $this->json('GET', route('list-phone-number-pools', [
            'company' => $company->id,
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            "result_count" => 1,
            "limit"        => 250,
            "page"         => 1,
            "total_pages"  =>  1,
            "next_page"    => null,
        ]);

        $response->assertJSONStructure([
            "results" => [
                [
                    'account_id',
                    'company_id',
                    'name',
                    'phone_number_config_id',
                    'swap_rules',
                    'link',
                    'kind'
                ]
            ]
        ]);
    }

    /** 
     * Test listing phone numbers pools with conditions
     * 
     * @group phone-number-pools
     */
    public function testListPhoneNumberPoolsWithConditions()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config);
        
        $conditions = [
            [
                'field'    => 'phone_number_pools.name',
                'operator' => 'EQUALS',
                'inputs'   => [
                    $pool->name
                ]
            ]
        ];

        $response = $this->json('GET', route('list-phone-number-pools', [
            'company' => $company->id
        ]), [
            'conditions' => json_encode($conditions)
        ]);

        $response->assertStatus(200);

        $response->assertJSONStructure([
            "results" => [
                [
                    'account_id',
                    'company_id',
                    'name',
                    'phone_number_config_id',
                    'swap_rules',
                    'link',
                    'kind'
                ]
            ]
        ]);


        $response->assertJSON([
            "result_count" => 1,
            "limit"        => 250,
            "page"         => 1,
            "total_pages"  =>  1,
            "next_page"    => null,
        ]);
    }

    /** 
     * Test listing phone numbers pools with date ranges
     * 
     * @group phone-number-pools
     */
    public function testListPhoneNumberPoolsWithDateRanges()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $twoDaysAgo  = now()->subDays(2);
        $pool        = $this->createPhoneNumberPool($company, $config, [
            'created_at' => $twoDaysAgo,
            'updated_at' => $twoDaysAgo
        ]);

        
        $twoDaysAgo->setTimeZone(new DateTimeZone($this->user->timezone));
        $response = $this->json('GET', route('list-phone-number-pools', [
            'company' => $company->id
        ]), [
            'start_date' => $twoDaysAgo->format('Y-m-d'),
            'end_date'   => $twoDaysAgo->format('Y-m-d')
        ]);

        $response->assertStatus(200);

        $response->assertJSON([
            "result_count" => 1,
            "limit"        => 250,
            "page"         => 1,
            "total_pages"  =>  1,
            "next_page"    => null,
            "results" => [
                [
                    'name'                   => $pool->name,
                    'phone_number_config_id' => $pool->phone_number_config_id,
                ]
            ]
        ]);
    }

    /**
     * Test listing phone numbers pool numbers
     * 
     * @group phone-number-pools
     */
    public function testListingPhoneNumberPoolNumbers()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config);

        $response = $this->json('GET', route('list-phone-number-pool-numbers', [
            'company' => $company->id,
            'phoneNumberPool' => $pool->id
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            "result_count" => 5,
            "limit"        => 250,
            "page"         => 1,
            "total_pages"  => 1,
            "next_page"    => null,
        ]);

        $response->assertJSONStructure([
            "results" => [
                [
                    'account_id',
                    'company_id',
                    'name',
                    'phone_number_config_id',
                    'swap_rules',
                    'link',
                    'kind'
                ]
            ]
        ]);
    }

     /**
     * Test listing phone numbers pool numbers with conditions
     * 
     * @group phone-number-pools
     */
    public function testListingPhoneNumberPoolNumbersWithConditions()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config);
        $firstNumber = $pool->phone_numbers->first();

        $conditions = [
            [
                'field'    => 'phone_numbers.name',
                'operator' => 'EQUALS',
                'inputs'   => [
                    $firstNumber->name
                ]
            ]
        ];

        $response = $this->json('GET', route('list-phone-number-pool-numbers', [
            'company' => $company->id,
            'phoneNumberPool' => $pool->id
        ]), [
            'conditions' => json_encode($conditions)
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            "result_count" => 1,
            "limit"        => 250,
            "page"         => 1,
            "total_pages"  => 1,
            "next_page"    => null,
        ]);

        $response->assertJSONStructure([
            "results" => [
                [
                    'account_id',
                    'company_id',
                    'name',
                    'phone_number_config_id',
                    'swap_rules',
                    'link',
                    'kind'
                ]
            ]
        ]);
    }

    /**
     * Test listing phone numbers pool numbers with date range
     * 
     * @group phone-number-pools
     */
    public function testListingPhoneNumberPoolNumbersWithDateRange()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config);
        $twoDaysAgo  = now()->subDays(2);
        $firstNumber = $pool->phone_numbers->first();

        $firstNumber->created_at = $twoDaysAgo;
        $firstNumber->save();
        
        $twoDaysAgo->setTimeZone(new DateTimeZone($this->user->timezone));
        $response = $this->json('GET', route('list-phone-number-pool-numbers', [
            'company'         => $company->id,
            'phoneNumberPool' => $pool->id
        ]), [
            'start_date' => $twoDaysAgo->format('Y-m-d'),
            'end_date'   => $twoDaysAgo->format('Y-m-d')
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            "result_count" => 1,
            "limit"        => 250,
            "page"         => 1,
            "total_pages"  => 1,
            "next_page"    => null,
            "results"      => [
                [
                    'id' => $firstNumber->id
                ]
            ]
        ]);

        $response->assertJSONStructure([
            "results" => [
                [
                    'account_id',
                    'company_id',
                    'name',
                    'phone_number_config_id',
                    'swap_rules',
                    'link',
                    'kind'
                ]
            ]
        ]);
    }

    /**
     * Test exporting phone numbers with conditions
     * 
     * @group phone-number-pools
     */
    public function testExportPhoneNumberPoolNumbersWithConditions()
    {
        Queue::fake();
        
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config);
        $firstNumber = $pool->phone_numbers->first();

        $conditions = [
            [
                'field'    => 'phone_numbers.name',
                'operator' => 'EQUALS',
                'inputs'   => [
                    $firstNumber->name
                ]
            ]
        ];

        $response = $this->json('GET', route('export-phone-number-pool-numbers', [
            'company' => $company->id,
            'phoneNumberPool' => $pool->id
        ]), [
            'conditions' => json_encode($conditions)
        ]);

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'queued'
        ]);

        Queue::assertPushed(ExportResultsJob::class, function ($job) use($conditions){
            return $job->model === PhoneNumberPool::class
                && $job->user->id === $this->user->id
                && json_decode($job->input['conditions'], true) == $conditions;
        });
    }

    /** 
     * Test exporting phone number pool numbers with date ranges
     * 
     * @group phone-number-pools
     */
    public function testExportPhoneNumberPoolNumbersWithDateRanges()
    {
        Queue::fake();

        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $twoDaysAgo  = now()->subDays(2);
        $pool        = $this->createPhoneNumberPool($company, $config);
        $numbers     = factory(PhoneNumber::class, 5)->create([
            'created_by' => $this->user->id,
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'phone_number_pool_id' => $pool->id,
            'phone_number_config_id' => $config->id,
            'created_at'             => $twoDaysAgo
        ]);
        
        $twoDaysAgo->setTimeZone(new DateTimeZone($this->user->timezone));
        $response = $this->json('GET', route('export-phone-number-pool-numbers', [
            'company' => $company->id,
            'phoneNumberPool' => $pool->id
        ]), [
            'start_date' => $twoDaysAgo->format('Y-m-d'),
            'end_date'   => $twoDaysAgo->format('Y-m-d')
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'queued'
        ]);

        Queue::assertPushed(ExportResultsJob::class, function ($job) use($twoDaysAgo){
            return $job->model === PhoneNumberPool::class 
                && $job->user->id === $this->user->id
                && $job->input['start_date'] == $twoDaysAgo->format('Y-m-d')
                && $job->input['end_date'] == $twoDaysAgo->format('Y-m-d');
        });
    }

    /*
     |-------------
     | Create
     |------------- 
     |
     | 
     */
     

    /**
     * Test user cannot create phone number pool when email not verified 
     *
     * @group phone-number-pools
     */
    public function testUserCannotCreatePhoneNumberPoolWhenEmailNotVerified()
    {
        $this->user->email_verified_at = null;
        $this->user->save();

        $company    = $this->createCompany();
        $config     = $this->createConfig($company);
        $poolData   = factory(PhoneNumberPool::class)->make();
        $areaCode   = '813'; 

        $response = $this->json('POST', route('create-phone-number-pool', [
            'company' => $company->id
        ]), [
            'name'                   => $poolData->name,
            'type'                   => PhoneNumber::TYPE_LOCAL,
            'starts_with'            => $areaCode,
            'phone_number_config_id' => $config->id,
            'swap_rules'             => json_encode($poolData->swap_rules)
        ]);

        $response->assertStatus(403);
        $response->assertJSON([
            'error' => 'Unauthorized'
        ]);

        $this->assertDatabaseMissing('phone_number_pools', [
            'company_id' => $company->id
        ]);
    }

    /** 
     * Test user cannot update phone number pool when email not verified 
     *
     * @group phone-number-pools
     */
    public function testUserCannotUpdatePhoneNumberPoolWhenEmailNotVerified()
    {
        $this->user->email_verified_at = null;
        $this->user->save();

        $company    = $this->createCompany();
        $config     = $this->createConfig($company);
        $pool       = $this->createPhoneNumberPool($company, $config);
        $newName    = str_random(10); 


        $response = $this->json('PUT', route('update-phone-number-pool', [
            'company' => $company->id,
            'phoneNumberPool' => $pool->id,
        ]), [
            'name' => $newName
        ]);

        $response->assertStatus(403);
        $response->assertJSON([
            'error' => 'Unauthorized'
        ]);

        $this->assertDatabaseMissing('phone_number_pools', [
            'id'   => $pool->id,
            'name' => $newName
        ]);
    }

    /**
     * Test user cannot pool before payment method added
     *
     * @group phone-number-pools
     */
    public function testUserCannotCreatePoolWithoutValidPaymentMethod()
    {
        $company    = $this->createCompany();
        $config     = $this->createConfig($company);
        $areaCode   = '813';
        $poolData   = factory(PhoneNumberPool::class)->make();

        $this->mock(PhoneNumberManager::class, function ($mock){
            $mock->shouldNotReceive('listAvailable');
            $mock->shouldNotReceive('purchase');
        });

        $response = $this->json('POST',route('create-phone-number-pool', [
            'company' => $company->id,
        ]), [
            'name' => $poolData->name,
            'type' => PhoneNumber::TYPE_LOCAL,
            'starts_with' => $areaCode,
            'size' => 5
        ]);

        $response->assertStatus(403);
        $response->assertJSONStructure([
            'error'
        ]);
    }

    /**
     * Test creating a local offline phone number pool
     * 
     * @group phone-number-pools
     */
    public function testCreateLocalPhoneNumberPool()
    {
        $this->createPaymentMethod();

        $company    = $this->createCompany();
        $config     = $this->createConfig($company);
        $poolData   = factory(PhoneNumberPool::class)->make();
        $poolSize   = 5;
        $areaCode   = '813'; 
        
        $twilioNumber = factory('Tests\Models\TwilioPhoneNumber')->make();

        $this->mock(PhoneNumberManager::class, function ($mock) use($areaCode, $company, $twilioNumber, $poolSize){
            $mock->shouldReceive('listAvailable')
                 ->once()
                 ->with($areaCode, $poolSize, PhoneNumber::TYPE_LOCAL, $company->country)
                 ->andReturn(
                    [$twilioNumber, $twilioNumber, $twilioNumber, $twilioNumber, $twilioNumber]
                );

            $mock->shouldReceive('purchase')
                ->times($poolSize)
                ->with($twilioNumber->phoneNumber)
                ->andReturn(
                   $twilioNumber
               );
        });

        $response = $this->json('POST', route('create-phone-number-pool', [
            'company' => $company->id
        ]), [
            'name'        => $poolData->name,
            'type'        => PhoneNumber::TYPE_LOCAL,
            'starts_with' => $areaCode,
            'phone_number_config_id' => $config->id,
            'swap_rules'  => json_encode($poolData->swap_rules),
            'size'         => $poolSize
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'account_id'  => $company->account_id,
            'company_id'  => $company->id,
            'name'        => $poolData->name,
            'phone_number_config_id' => $config->id,
            'swap_rules'   => json_decode(json_encode($poolData->swap_rules), true),
            'link'         => route('read-phone-number-pool', [
                'company'         => $company->id,
                'phoneNumberPool' => $response['id']
            ]),
            'kind'             => 'PhoneNumberPool',
        ]);

        $this->assertDatabaseHas('phone_number_pools', [
            'company_id' => $company->id,
            'account_id' => $company->account_id,
            'id' => $response['id']
        ]);
    }

    /**
     * Test creating toll-free phone number pool
     * 
     * @group phone-number-pools
     */
    public function testCreateTollFreePhoneNumberPool()
    {
        $this->createPaymentMethod();

        $company    = $this->createCompany();
        $config     = $this->createConfig($company);
        $poolData   = factory(PhoneNumberPool::class)->make();
        $poolSize   = 5;

        $twilioNumber = factory('Tests\Models\TwilioPhoneNumber')->make();

        $this->mock(PhoneNumberManager::class, function ($mock) use($company, $twilioNumber, $poolSize){
            $mock->shouldReceive('listAvailable')
                 ->once()
                 ->with('', $poolSize, PhoneNumber::TYPE_TOLL_FREE, $company->country)
                 ->andReturn(
                    [$twilioNumber, $twilioNumber, $twilioNumber, $twilioNumber, $twilioNumber]
                );

            $mock->shouldReceive('purchase')
                ->times($poolSize)
                ->with($twilioNumber->phoneNumber)
                ->andReturn(
                   $twilioNumber
               );
        });

        $response = $this->json('POST', route('create-phone-number-pool', [
            'company' => $company->id
        ]), [
            'name'        => $poolData->name,
            'type'        => PhoneNumber::TYPE_TOLL_FREE,
            'phone_number_config_id' => $config->id,
            'swap_rules'  => json_encode($poolData->swap_rules),
            'size'         => $poolSize
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'account_id'  => $company->account_id,
            'company_id'  => $company->id,
            'name'        => $poolData->name,
            'phone_number_config_id' => $config->id,
            'swap_rules'   => json_decode(json_encode($poolData->swap_rules), true),
            'link'         => route('read-phone-number-pool', [
                'company'         => $company->id,
                'phoneNumberPool' => $response['id']
            ]),
            'kind'             => 'PhoneNumberPool',
        ]);

        $this->assertDatabaseHas('phone_number_pools', [
            'company_id' => $company->id,
            'account_id' => $company->account_id,
            'id'         => $response['id']
        ]);
    }

    /**
     * Test creating a phone number pool from local banked numbers
     * 
     * @group phone-number-pools
     */
    public function testCreatePhoneNumberPoolFromLocalBank()
    {
        $this->createPaymentMethod();

        $otherAccount  = $this->createAccount(); 
        $company       = $this->createCompany();
        $config        = $this->createConfig($company);
        $poolData      = factory(PhoneNumberPool::class)->make();
        $poolSize      = 5;

        $bankedNumbers = factory(BankedPhoneNumber::class, $poolSize)->create([
            'released_by_account_id' => $otherAccount->id,
            'number'                 => '813' . mt_rand(1111111,9999999),
            'status'                 => 'Available',
            'type'                   => PhoneNumber::TYPE_LOCAL
        ]);

        $this->mock(PhoneNumberManager::class, function ($mock){
            $mock->shouldNotReceive('listAvailable');
            $mock->shouldNotReceive('purchase');
        });

        $response = $this->json('POST', route('create-phone-number-pool', [
            'company' => $company->id
        ]), [
            'name'         => $poolData->name,
            'type'         => PhoneNumber::TYPE_LOCAL,
            'size'         => $poolSize,
            'starts_with'  => '813',
            'phone_number_config_id' => $config->id,
            'swap_rules'  => json_encode($poolData->swap_rules)
        ]);

        $response->assertStatus(201);

        $response->assertJSON([
            'account_id'  => $company->account_id,
            'company_id'  => $company->id,
            'name'        => $poolData->name,
            'phone_number_config_id' => $config->id,
            
            'swap_rules'   => json_decode(json_encode($poolData->swap_rules), true),
            'link'         => route('read-phone-number-pool', [
                'company' => $company->id,
                'phoneNumberPool' => $response['id']
            ]),
            'kind'             => 'PhoneNumberPool',
        ]);

        $bankedNumbers->each(function($bankedNumber){
            $this->assertDatabaseMissing('banked_phone_numbers', [
                'id'         => $bankedNumber->id,
                'deleted_at' => null
            ]);
        });
    }

    /**
     * Test creating a phone number pool from toll-free banked numbers
     * 
     * @group phone-number-pools
     */
    public function testCreateTollFreePhoneNumberPoolFromBank()
    {
        $this->createPaymentMethod();

        $otherAccount  = $this->createAccount(); 
        $company       = $this->createCompany();
        $config        = $this->createConfig($company);
        $poolData      = factory(PhoneNumberPool::class)->make();
        $poolSize      = 5;

        $bankedNumbers = factory(BankedPhoneNumber::class, $poolSize)->create([
            'released_by_account_id' => $otherAccount->id,
            'number'                 => '813' . mt_rand(1111111,9999999),
            'status'                 => 'Available',
            'type'                   => PhoneNumber::TYPE_TOLL_FREE
        ]);

        $this->mock(PhoneNumberManager::class, function ($mock){
            $mock->shouldNotReceive('listAvailable');
            $mock->shouldNotReceive('purchase');
        });

        $response = $this->json('POST', route('create-phone-number-pool', [
            'company' => $company->id
        ]), [
            'name'         => $poolData->name,
            'type'         => PhoneNumber::TYPE_TOLL_FREE,
            'size'         => $poolSize,
            'starts_with'  => '813',
            'phone_number_config_id' => $config->id,
            'swap_rules'  => json_encode($poolData->swap_rules)
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'account_id'  => $company->account_id,
            'company_id'  => $company->id,
            'name'        => $poolData->name,
            'phone_number_config_id' => $config->id,
            
            'swap_rules'   => json_decode(json_encode($poolData->swap_rules), true),
            'link'         => route('read-phone-number-pool', [
                'company' => $company->id,
                'phoneNumberPool' => $response['id']
            ]),
            'kind'             => 'PhoneNumberPool',
        ]);

        $bankedNumbers->each(function($bankedNumber){
            $this->assertDatabaseMissing('banked_phone_numbers', [
                'id'         => $bankedNumber->id,
                'deleted_at' => null
            ]);
        });   
    }

    /**
     * Test adding a phone number to a pool fails when email is not verified
     * 
     * @group phone-number-pools
     */
    public function testAddingPhoneNumberToPoolFailsWhenEmailNotVerified()
    {
        $this->createPaymentMethod();

        $this->user->email_verified_at = null;
        $this->user->save();

        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config);

        $response = $this->json('POST', route('add-phone-number-pool-numbers', [
            'company' => $company->id,
            'phoneNumberPool' => $pool->id,
        ]), [
            'count' => 5,
            'type'  => PhoneNumber::TYPE_LOCAL,
            'starts_with' => '813'
        ]);

        $response->assertStatus(403);
        $response->assertJSONStructure([
            'error'
        ]);
    }

     /**
     * Test adding a phone number to a pool fails when no payment method is added
     * 
     * @group phone-number-pools
     */
    public function testAddingPhoneNumberToPoolFailsWhenNoValidPaymentMethodAdded()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config);

        $response = $this->json('POST', route('add-phone-number-pool-numbers', [
            'company' => $company->id,
            'phoneNumberPool' => $pool->id,
        ]), [
            'count' => 5,
            'type'  => PhoneNumber::TYPE_LOCAL,
            'starts_with' => '813'
        ]);

        $response->assertStatus(403);
        $response->assertJSONStructure([
            'error'
        ]);
    }

    /**
     * Test banked phone numbers are used when available
     * 
     * @group phone-number-pools
     */
    public function testAddingPhoneNumberToPoolUsesAvailableBankedNumbers()
    {
        $this->createPaymentMethod();

        $company      = $this->createCompany();
        $config       = $this->createConfig($company);
        $pool         = $this->createPhoneNumberPool($company, $config);
        $otherAccount = $this->createAccount();
        $count        = 5;
        $areaCode     = '813';

        $bankedNumbers = factory(BankedPhoneNumber::class, 2)->create([
            'released_by_account_id' => $otherAccount->id,
            'number'                 => $areaCode . mt_rand(1111111,9999999),
            'status'                 => 'Available',
            'type'                   => PhoneNumber::TYPE_LOCAL
        ]);

        $twilioNumber = factory('Tests\Models\TwilioPhoneNumber')->make();
        $this->mock(PhoneNumberManager::class, function ($mock) use($count, $company, $areaCode, $twilioNumber){
            $mock->shouldReceive('listAvailable')
                 ->once()
                 ->with($areaCode, $count, PhoneNumber::TYPE_LOCAL, $company->country)
                 ->andReturn(
                    [$twilioNumber, $twilioNumber, $twilioNumber, $twilioNumber, $twilioNumber]
                );

            $mock->shouldReceive('purchase')
                 ->times(3)
                 ->with($twilioNumber->phoneNumber)
                 ->andReturn(
                   $twilioNumber
                );
        });

        $response = $this->json('POST', route('add-phone-number-pool-numbers', [
            'company' => $company->id,
            'phoneNumberPool' => $pool->id,
        ]), [
            'count' => $count,
            'type'  => PhoneNumber::TYPE_LOCAL,
            'starts_with' => $areaCode
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'message' => 'Added',
            'count'   => $count,
        ]);

        foreach( $bankedNumbers as $bankedNumber ){
            $this->assertDatabaseMissing('banked_phone_numbers', [
                'id'         => $bankedNumber->id,
                'deleted_at' => null
            ]);

            $this->assertDatabaseHas('phone_numbers', [
                'number'     => $bankedNumber->number,
                'deleted_at' => null
            ]);
        }
    }

    /**
     * Test attaching existing numbers to existing pool
     * 
     * @group phone-number-pools
     */
    public function testAttachingExistingNumberToPool()
    {
        $this->createPaymentMethod();

        $company      = $this->createCompany();
        $config       = $this->createConfig($company);
        $pool         = $this->createPhoneNumberPool($company, $config);
        $phoneNumber  = $this->createPhoneNumber($company, $config);

        $response = $this->json('POST', route('attach-phone-number-pool-numbers', [
            'company' => $company->id,
            'phoneNumberPool' => $pool->id,
        ]), [
            'ids' => json_encode([$phoneNumber->id])
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'Attached',
            'count'   => 1,
        ]);

        $this->assertDatabaseHas('phone_numbers', [
            'id'                    => $phoneNumber->id,
            'phone_number_pool_id'  => $pool->id,
            'assignments'           => 0,
            'disabled_at'           => null,
            'last_assigned_at'      => null
        ]);
    }

     /**
     * Test detaching existing numbers from existing pool
     * 
     * @group phone-number-pools
     */
    public function testDetachingExistingNumbersFromPool()
    {
        $this->createPaymentMethod();

        $company      = $this->createCompany();
        $config       = $this->createConfig($company);
        $pool         = $this->createPhoneNumberPool($company, $config);
        $phoneNumber  = $pool->phone_numbers->first();
        $response = $this->json('POST', route('detach-phone-number-pool-numbers', [
            'company' => $company->id,
            'phoneNumberPool' => $pool->id,
        ]), [
            'ids' => json_encode([$phoneNumber->id])
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'Detached',
            'count'   => 1,
        ]);

        $this->assertDatabaseHas('phone_numbers', [
            'id'                    => $phoneNumber->id,
            'phone_number_pool_id'  => null,
            'assignments'           => 0,
            'disabled_at'           => null,
            'last_assigned_at'      => null
        ]);
    }

    /*
     |-------------
     | Update
     |------------- 
     |
     | 
     */

    /**
     * Test disabling and enabling a phone number pool
     * 
     * @group phone-number-pools
     */
    public function testDisableEnablePhoneNumberPool()
    {
        $company = $this->createCompany();
        $config  = $this->createConfig($company);
        $pool    = $this->createPhoneNumberPool($company, $config, [
            'disabled_at' => null
        ]); 
        $expectedData = json_decode(json_encode($pool->toArray()), true);
        unset($expectedData['disabled_at']); // Disabled time may differ by seconds from now
        unset($expectedData['updated_at']);
        
        //  Disable
        $response = $this->json('PUT', route('update-phone-number-pool', [
            'company'         => $company->id,
            'phoneNumberPool' => $pool->id
        ]), [
            'disabled' => 1
        ]);
        $response->assertStatus(200);
        $response->assertJSON($expectedData);
        $this->assertNotNull($response['disabled_at']);

        //  Re-enable
        $response = $this->json('PUT', route('update-phone-number-pool', [
            'company'         => $company->id,
            'phoneNumberPool' => $pool->id
        ]), [
            'disabled' => 0
        ]);

        $response->assertStatus(200);
        $response->assertJSON($expectedData);
        $this->assertNull($response['disabled_at']);
    }

    /** 
     * Test updating a phone number pool
     * 
     * @group phone-number-pools
     */
    public function testUpdatePhoneNumberPool()
    {
        $company = $this->createCompany();
        $config  = $this->createConfig($company);
        $pool    = $this->createPhoneNumberPool($company, $config); 
        $poolData = factory(PhoneNumberPool::class)->make();

        $expectedData = json_decode(json_encode($pool->toArray()), true);
        unset($expectedData['disabled_at']); // Disabled time may differ by seconds from now
        unset($expectedData['updated_at']);
        
        //  Disable
        $response = $this->json('PUT', route('update-phone-number-pool', [
            'company'         => $company->id,
            'phoneNumberPool' => $pool->id
        ]), [
            'name' => $poolData->name
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'name' => $poolData->name,
        ]);
    }

    /**
     * Test reading a phone number pool
     * 
     * @group phone-number-pools
     */
    public function testReadPhoneNumberPool()
    {
        $company = $this->createCompany();
        $config  = $this->createConfig($company);
        $pool    = $this->createPhoneNumberPool($company, $config, [
            'disabled_at' => null
        ]); 
        
        $response = $this->json('GET', route('read-phone-number-pool', [
            'company' => $company->id,
            'phoneNumberPool' => $pool->id
        ]));
        
        $response->assertStatus(200);
        $response->assertJSON([
            'id'                        => $pool->id,
            'account_id'                => $pool->account_id,
            'company_id'                => $pool->company_id,
            'name'                      => $pool->name,
            'phone_number_config_id'    => $pool->phone_number_config_id,
            'link'                      => $pool->link,
            'kind'                      => $pool->kind,
            'swap_rules'                => json_decode(json_encode($pool->swap_rules), true)
        ]);
    }

    /**
     * Test deleting a phone number pool will release numbers because the renewal date is within 5 days
     * 
     * @group phone-number-pools
     */
    public function testDeletePhoneNumberPoolNumbersIsReleasedForRenewalDate()
    {
        $company = $this->createCompany();
        $config  = $this->createConfig($company);
        $pool    = $this->createPhoneNumberPool($company, $config); 

        $pool->phone_numbers->each(function($phoneNumber){
            $phoneNumber->purchased_at = now()->subMonths(1)->addDays(5); // Renews in 4 days
            $phoneNumber->save();
        });

        $this->mock(PhoneNumberManager::class, function ($mock){
            $mock->shouldReceive('releaseNumber')
                 ->times(5)
                 ->with(PhoneNumber::class);
        });

        $response = $this->json('DELETE', route('delete-phone-number-pool', [
            'company'     => $company->id,
            'phoneNumberPool' => $pool->id
        ]));
    }

    /**
     * Test deleting a phone number pool will release all numbers because there are too many calls for each
     * 
     * @group phone-number-pools
     */
    public function testDeletePhoneNumberPoolBumbersAreReleasedForTooManyCalls()
    {
        $company = $this->createCompany();
        $config  = $this->createConfig($company);
        $pool    = $this->createPhoneNumberPool($company, $config); 

        $poolNumbers = $pool->phone_numbers;
        $poolNumbers->each(function($phoneNumber) use($company){
            factory(Call::class, 30)->create([
                'account_id'      => $company->account_id,
                'company_id'      => $company->id,
                'phone_number_id' => $phoneNumber->id
            ]);
        });

        $this->mock(PhoneNumberManager::class, function ($mock){
            $mock->shouldReceive('releaseNumber')
                 ->times(5)
                 ->with(PhoneNumber::class);
        });

        $response = $this->json('DELETE', route('delete-phone-number-pool', [
            'company'     => $company->id,
            'phoneNumberPool' => $pool->id
        ]));

        $this->assertDatabaseMissing('phone_number_pools', [
            'id'         => $pool->id,
            'deleted_at' => null
        ]);

        foreach( $poolNumbers as $phoneNumber ){
            $this->assertDatabaseMissing('phone_numbers', [
                'id'         => $phoneNumber->id,
                'deleted_at' => null
            ]);
        }
    }

    /**
     * Test deleting a phone number pool will bank numbers because the renewal dates are after 5 days
     * 
     * @group phone-number-pools
     */
    public function testDeletePhoneNumberPoolNumbersAreBankedForRenewalDate()
    {
        $company = $this->createCompany();
        $config  = $this->createConfig($company);
        $pool    = $this->createPhoneNumberPool($company, $config); 

        $poolNumbers = $pool->phone_numbers;
        $poolNumbers->each(function($phoneNumber) use($company){
            $phoneNumber->purchased_at = now()->subMonths(1)->addDays(6); // Renews in 6 days
            $phoneNumber->save();

            factory(Call::class, 10)->create([
                'account_id'      => $company->account_id,
                'company_id'      => $company->id,
                'phone_number_id' => $phoneNumber->id
            ]);
        });

        $this->mock(PhoneNumberManager::class, function ($mock){
            $mock->shouldReceive('bankNumber')
                 ->times(5)
                 ->with(PhoneNumber::class, false);
        });

        $response = $this->json('DELETE', route('delete-phone-number-pool', [
            'company'         => $company->id,
            'phoneNumberPool' => $pool->id
        ]));

        $response->assertStatus(200);
        
        $response->assertJSON([
            'message' => 'Deleted'
        ]);

        $this->assertDatabaseMissing('phone_number_pools', [
            'id'         => $pool->id,
            'deleted_at' => null
        ]);

        foreach( $poolNumbers as $phoneNumber ){
            $this->assertDatabaseMissing('phone_numbers', [
                'id'         => $phoneNumber->id,
                'deleted_at' => null
            ]);

            $this->assertDatabaseMissing('banked_phone_numbers', [
                'external_id' => $phoneNumber->external_id,
                'number'     => $phoneNumber->number,
                'deleted_at' => null
            ]);
        }
    }

    /**
     * Test deleting a phone number pool's numbers will be banked and available for low call volume
     * 
     * @group phone-number-pools
     */
    public function testDeletePhoneNumberPoolNumbersAreBankedAndAvailableForLowCallVolume()
    {
        $company = $this->createCompany();
        $config  = $this->createConfig($company);
        $pool    = $this->createPhoneNumberPool($company, $config); 

        $poolNumbers = $pool->phone_numbers;
        $poolNumbers->each(function($phoneNumber) use($company){
            $phoneNumber->purchased_at = now()->subMonths(1)->addDays(6); // Renews in 6 days
            $phoneNumber->save();

            factory(Call::class, 9)->create([
                'account_id'      => $company->account_id,
                'company_id'      => $company->id,
                'phone_number_id' => $phoneNumber->id
            ]);
        });

        $this->mock(PhoneNumberManager::class, function ($mock){
            $mock->shouldReceive('bankNumber')
                 ->times(5)
                 ->with(PhoneNumber::class, true);
        });

        $response = $this->json('DELETE', route('delete-phone-number-pool', [
            'company'         => $company->id,
            'phoneNumberPool' => $pool->id
        ]));

        $response->assertStatus(200);
        
        $response->assertJSON([
            'message' => 'Deleted'
        ]);

        $this->assertDatabaseMissing('phone_number_pools', [
            'id'         => $pool->id,
            'deleted_at' => null
        ]);

        foreach( $poolNumbers as $phoneNumber ){
            $this->assertDatabaseMissing('phone_numbers', [
                'id'         => $phoneNumber->id,
                'deleted_at' => null
            ]);

            $this->assertDatabaseMissing('banked_phone_numbers', [
                'external_id' => $phoneNumber->external_id,
                'number'     => $phoneNumber->number,
                'deleted_at' => null
            ]);
        }
    }
}
