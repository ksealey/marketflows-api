<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Mockery;
use \Twilio\Rest\Client as Twilio;
use \App\Models\Account;
use \App\Models\Company\PhoneNumberConfig;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\Call;
use \App\Services\PhoneNumberService;
use App\Services\ExportService;
use DateTimeZone;
use Queue;

class PhoneNumberTest extends TestCase
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
     * Test listing phone numbers
     * 
     * @group phone-numbers
     */
    public function testListingPhoneNumbers()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        
        factory(PhoneNumber::class, 10)->create([
            'account_id'             => $this->account->id,
            'company_id'             => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by'             => $this->user->id
        ]);

        $response = $this->json('GET', route('list-phone-numbers', [
            'company' => $company->id,
            'date_type' => 'ALL_TIME'
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            "result_count" => 10,
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
                    'category',
                    'sub_category',
                    'type',
                    'source',
                    'medium',
                    'content',
                    'campaign',
                    'phone_number_config_id',
                    'country',
                    'country_code',
                    'number',
                    'swap_rules',
                    'call_count',
                    'link',
                    'kind'
                ]
            ]
        ]);
    }

    /**
     * Test listing phone numbers with conditions
     * 
     * @group phone-numbers
     */
    public function testListPhoneNumbersWithConditions()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        
        $phoneNumbers = factory(PhoneNumber::class, 10)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by' => $this->user->id,
            'swap_rules' => $this->makeSwapRules()
        ]);

        $firstNumber = $phoneNumbers->first();

        $conditions = [
            [
                [
                    'field'    => 'phone_numbers.name',
                    'operator' => 'EQUALS',
                    'inputs'   => [
                        $firstNumber->name
                    ]
                ]
            ]
        ];

        $response = $this->json('GET', route('list-phone-numbers', [
            'company' => $company->id
        ]), [
            'conditions' => json_encode($conditions),
            'date_type'  => 'ALL_TIME'
        ]);

        $response->assertStatus(200);

        $response->assertJSON([
            "result_count" => 1,
            "limit"        => 250,
            "page"         => 1,
            "total_pages"  =>  1,
            "next_page"    => null,
        ]);
    }

    /**
     * Test listing phone numbers with date ranges
     * 
     * @group phone-numbers
     */
    public function testListPhoneNumbersWithDateRanges()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);

        $twoDaysAgo  = now()->subDays(2);

        $oldPhoneNumber  = factory(PhoneNumber::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by' => $this->user->id,
            'created_at' => $twoDaysAgo,
            'updated_at' => $twoDaysAgo
        ]);

        $phoneNumbers = factory(PhoneNumber::class, 10)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by' => $this->user->id
        ]);
        
        $twoDaysAgo->setTimeZone(new DateTimeZone($this->user->timezone));
        $response = $this->json('GET', route('list-phone-numbers', [
            'company' => $company->id
        ]), [
            'date_type'  => 'CUSTOM',
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
                    'name'        => $oldPhoneNumber->name,
                    'category'    => $oldPhoneNumber->category,
                    'sub_category'=> $oldPhoneNumber->sub_category,
                    'type'        => $oldPhoneNumber->type,
                    'source'      => $oldPhoneNumber->source,
                    'medium'      => $oldPhoneNumber->medium,
                    'content'     => $oldPhoneNumber->content,
                    'campaign'    => $oldPhoneNumber->campaign,
                    'phone_number_config_id' => $oldPhoneNumber->phone_number_config_id,
                ]
            ]
        ]);
    }

    /**
     * Test exportng phone numbers
     * 
     * @group phone-numbers
     */
    public function testExportPhoneNumbers()
    {
        $exportData  = bin2hex(random_bytes(200));
        $this->mock(ExportService::class, function($mock) use($exportData){
            $mock->shouldReceive('exportAsOutput')
                 ->once()
                 ->andReturn($exportData);
        });

        $company = $this->createCompany();
        $config  = $this->createConfig($company);

        $phoneNumbers = factory(PhoneNumber::class, 10)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('GET', route('export-phone-numbers', [
            'company' => $company->id
        ]));

        $response->assertStatus(200);
        $response->assertSee($exportData);
    }

    /**
     * Test exporting phone numbers with conditions
     * 
     * @group phone-numbers
     */
    public function testExportPhoneNumbersWithConditions()
    {
        $exportData  = bin2hex(random_bytes(200));
        $this->mock(ExportService::class, function($mock) use($exportData){
            $mock->shouldReceive('exportAsOutput')
                 ->once()
                 ->andReturn($exportData);
        });

        $company     = $this->createCompany();
        $config      = $this->createConfig($company);

        $phoneNumbers = factory(PhoneNumber::class, 10)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by' => $this->user->id
        ]);

        $conditions = [
            [
                [
                    'field'    => 'phone_numbers.name',
                    'operator' => 'EQUALS',
                    'inputs'   => [
                        $phoneNumbers->first()->name
                    ]
                ]
            ]
        ];

        $response = $this->json('GET', route('export-phone-numbers', [
            'company' => $company->id
        ]), [
            'conditions' => json_encode($conditions)
        ]);
        $response->assertStatus(200);
        $response->assertSee($exportData);
    }

    /**
     * Test exporting phone numbers with date ranges
     * 
     * @group phone-numbers
     */
    public function testExportPhoneNumbersWithDateRanges()
    {
        $exportData  = bin2hex(random_bytes(200));
        $this->mock(ExportService::class, function($mock) use($exportData){
            $mock->shouldReceive('exportAsOutput')
                 ->once()
                 ->andReturn($exportData);
        });

        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $twoDaysAgo  = now()->subDays(2);

        $oldPhoneNumber  = factory(PhoneNumber::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by' => $this->user->id,
            'created_at' => $twoDaysAgo,
            'updated_at' => $twoDaysAgo
        ]);

        $phoneNumbers = factory(PhoneNumber::class, 10)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by' => $this->user->id
        ]);

        
        $twoDaysAgo->setTimeZone(new DateTimeZone($this->user->timezone));
        $response = $this->json('GET', route('export-phone-numbers', [
            'company' => $company->id
        ]), [
            'date_type'  => 'CUSTOM',
            'start_date' => $twoDaysAgo->format('Y-m-d'),
            'end_date'   => $twoDaysAgo->format('Y-m-d')
        ]);

        $response->assertStatus(200);
        $response->assertSee($exportData);
    }

   

    /**
     * Test checking available numbers
     *
     * @group phone-numbers
     */
    public function testCheckingAvailableNumbers()
    {
        $otherAccount = $this->createAccount();
        $company      = $this->createCompany();
        $type         = mt_rand(0,1) ? PhoneNumber::TYPE_LOCAL : PhoneNumber::TYPE_TOLL_FREE;
        $count        = mt_rand(1, 10);

        $this->mock(PhoneNumberService::class, function ($mock) use($count, $type, $company){
            $returnNumbers = factory('Tests\Models\TwilioPhoneNumber', $count)->make();

            $mock->shouldReceive('listAvailable')
                 ->once()
                 ->with('813', $count, $type, $company->country)
                 ->andReturn($returnNumbers);
        });

        $response = $this->json('GET', route('phone-numbers-available', [
            'company' => $company->id
        ]), [
            'type'          => $type,
            'count'         => $count,
            'starts_with'   => '813'
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'available' => true,
            'count'     => $count,
            'type'      => $type
        ]);
    }

    /**
     * Test checking available numbers with not enough numbers
     *
     * @group phone-numbers
     */
    public function testCheckingAvailableNumbersWithNotEnoughNumbers()
    {
        $otherAccount = $this->createAccount();
        $company      = $this->createCompany();
        $type         = mt_rand(0,1) ? PhoneNumber::TYPE_LOCAL : PhoneNumber::TYPE_TOLL_FREE;
        $count        = mt_rand(6, 10);

        $this->mock(PhoneNumberService::class, function ($mock) use($count, $type, $company){
            $returnNumbers = factory('Tests\Models\TwilioPhoneNumber', $count - 5)->make();

            $mock->shouldReceive('listAvailable')
                 ->once()
                 ->with('813', $count, $type, $company->country)
                 ->andReturn($returnNumbers);
        });

        $response = $this->json('GET', route('phone-numbers-available', [
            'company' => $company->id
        ]), [
            'type'          => $type,
            'count'         => $count,
            'starts_with'   => '813'
        ]);

        $response->assertStatus(400);
        $response->assertJSON([
            'available' => false,
            'count'     => $count - 5,
            'type'      => $type
        ]);
    }

    /**
     * Test checking available numbers with no numbers
     *
     * @group phone-numbers
     */
    public function testCheckingAvailableNumbersWithNoNumbers()
    {
        $otherAccount = $this->createAccount();
        $company      = $this->createCompany();
        $type         = mt_rand(0,1) ? PhoneNumber::TYPE_LOCAL : PhoneNumber::TYPE_TOLL_FREE;
        $count        = mt_rand(1, 10);

        $this->mock(PhoneNumberService::class, function ($mock) use($count, $type, $company){
            $mock->shouldReceive('listAvailable')
                 ->once()
                 ->with('813', $count, $type, $company->country)
                 ->andReturn([]);
        });

        $response = $this->json('GET', route('phone-numbers-available', [
            'company' => $company->id
        ]), [
            'type'          => $type,
            'count'         => $count,
            'starts_with'   => '813'
        ]);

        $response->assertStatus(400);
        $response->assertJSON([
            'available' => false,
            'count'     => 0,
            'type'      => $type
        ]);
    }

    /*
     |-------------
     | Create
     |------------- 
     |
     | 
     */

    /**
     * Test creating a local offline phone number
     * 
     * @group phone-numbers
     */
    public function testCreateLocalPhoneNumber()
    {
        $company    = $this->createCompany();
        $config     = $this->createConfig($company);
        $numberData = factory(PhoneNumber::class)->make();
        $areaCode   = '813'; 
        
        $twilioNumber = factory('Tests\Models\TwilioPhoneNumber')->make();

        $this->mock(PhoneNumberService::class, function ($mock) use($areaCode, $company, $twilioNumber){
            $mock->shouldReceive('listAvailable')
                 ->once()
                 ->with($areaCode, 1, PhoneNumber::TYPE_LOCAL, $company->country)
                 ->andReturn(
                    [$twilioNumber]
                );

            $mock->shouldReceive('purchase')
                ->once()
                ->with($twilioNumber->phoneNumber)
                ->andReturn(
                   $twilioNumber
               );
        });


        $response = $this->json('POST', route('create-phone-number', [
            'company' => $company->id
        ]), [
            'name'        => $numberData->name,
            'category'    => $numberData->category,
            'sub_category'=> $numberData->sub_category,
            'type'        => PhoneNumber::TYPE_LOCAL,
            'starts_with' => $areaCode,
            'source'      => $numberData->source,
            'medium'      => $numberData->medium,
            'content'     => $numberData->content,
            'campaign'    => $numberData->campaign,
            'phone_number_config_id' => $config->id,
            'swap_rules'  => json_encode($numberData->swap_rules)
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'account_id'  => $company->account_id,
            'company_id'  => $company->id,
            'name'        => $numberData->name,
            'category'    => $numberData->category,
            'sub_category'=> $numberData->sub_category,
            'type'        => PhoneNumber::TYPE_LOCAL,
            'source'      => $numberData->source,
            'medium'      => $numberData->medium,
            'content'     => $numberData->content,
            'campaign'     => $numberData->campaign,
            'phone_number_config_id' => $config->id,
            'country'      => $company->country,
            'country_code' => PhoneNumber::countryCode($twilioNumber->phoneNumber),
            'number'       => PhoneNumber::number($twilioNumber->phoneNumber),
            'swap_rules'   => $numberData->sub_category == 'WEBSITE' ? json_decode(json_encode($numberData->swap_rules), true)  : null,
            'call_count'   => 0,
            'link'         => route('read-phone-number', [
                'company' => $company->id,
                'phoneNumber' => $response['id']
            ]),
            'kind'             => 'PhoneNumber',
        ]);

        $this->assertDatabaseHas('phone_numbers', [
            'company_id' => $company->id,
            'account_id' => $company->account_id,
            'id' => $response['id']
        ]);
    }

    /**
     * Test creating a toll-free phone number
     * 
     * @group phone-numbers
     */
    public function testCreateTollFreePhoneNumber()
    {
        $company    = $this->createCompany();
        $config     = $this->createConfig($company);

        $numberData   = factory(PhoneNumber::class)->make();        
        $twilioNumber = factory('Tests\Models\TwilioPhoneNumber')->make();
        $this->mock(PhoneNumberService::class, function ($mock) use($company, $twilioNumber){
            $mock->shouldReceive('listAvailable')
                 ->once()
                 ->with('', 1, PhoneNumber::TYPE_TOLL_FREE, $company->country)
                 ->andReturn(
                    [$twilioNumber]
                );

            $mock->shouldReceive('purchase')
                ->once()
                ->with($twilioNumber->phoneNumber)
                ->andReturn(
                   $twilioNumber
               );
        });

        $response = $this->json('POST', route('create-phone-number', [
            'company' => $company->id
        ]), [
            'name'        => $numberData->name,
            'category'    => $numberData->category,
            'sub_category'=> $numberData->sub_category,
            'type'        => PhoneNumber::TYPE_TOLL_FREE,
            'source'      => $numberData->source,
            'medium'      => $numberData->medium,
            'content'     => $numberData->content,
            'campaign'    => $numberData->campaign,
            'phone_number_config_id' => $config->id,
            'swap_rules'  => json_encode($numberData->swap_rules)
        ]);

        $response->assertStatus(201);

        $response->assertJSON([
            'account_id'  => $company->account_id,
            'company_id'  => $company->id,
            'name'        => $numberData->name,
            'category'    => $numberData->category,
            'sub_category'=> $numberData->sub_category,
            'type'        => PhoneNumber::TYPE_TOLL_FREE,
            'source'      => $numberData->source,
            'medium'      => $numberData->medium,
            'content'     => $numberData->content,
            'campaign'        => $numberData->campaign,
            'phone_number_config_id' => $config->id,
            'country'      => $company->country,
            'swap_rules'   => $numberData->sub_category == 'WEBSITE' ?  json_decode(json_encode($numberData->swap_rules), true)  : null,
            'call_count'   => 0,
            'link'         => route('read-phone-number', [
                'company' => $company->id,
                'phoneNumber' => $response['id']
            ]),
            'kind'             => 'PhoneNumber',
        ]);

        $this->assertDatabaseHas('phone_numbers', [
            'company_id' => $company->id,
            'account_id' => $company->account_id,
            'id'         => $response['id']
        ]);
    }

    /**
     * Test creating an online social phone number. When not WEBSITE, swap rules should be null.
     * 
     * @group phone-numbers
     */
    public function testCreateOnlineSocialPhoneNumber()
    {
        $company    = $this->createCompany();
        $config     = $this->createConfig($company);

        $numberData = factory(PhoneNumber::class)->make([
            'category' => 'ONLINE',
            'sub_category' => 'SOCIAL_MEDIA'
        ]);

        $areaCode   = '813'; 
        if( $numberData->type === PhoneNumber::TYPE_TOLL_FREE )
            $areaCode   = ''; 
        
        $twilioNumber = factory('Tests\Models\TwilioPhoneNumber')->make();

        $this->mock(PhoneNumberService::class, function ($mock) use($numberData, $areaCode, $company, $twilioNumber){
            $mock->shouldReceive('listAvailable')
                 ->once()
                 ->with($areaCode, 1, $numberData->type, $company->country)
                 ->andReturn(
                    [$twilioNumber]
                );

            $mock->shouldReceive('purchase')
                ->once()
                ->with($twilioNumber->phoneNumber)
                ->andReturn(
                   $twilioNumber
               );
        });

        $response = $this->json('POST', route('create-phone-number', [
            'company' => $company->id
        ]), [
            'name'        => $numberData->name,
            'category'    => $numberData->category,
            'sub_category'=> $numberData->sub_category,
            'type'        => $numberData->type,
            'starts_with' => $areaCode,
            'source'      => $numberData->source,
            'medium'      => $numberData->medium,
            'content'     => $numberData->content,
            'campaign'    => $numberData->campaign,
            'phone_number_config_id' => $config->id,
            'swap_rules'  => json_encode($numberData->swap_rules)
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'account_id'  => $company->account_id,
            'company_id'  => $company->id,
            'name'        => $numberData->name,
            'category'    => $numberData->category,
            'sub_category'=> $numberData->sub_category,
            'type'        => $numberData->type,
            'source'      => $numberData->source,
            'medium'      => $numberData->medium,
            'content'     => $numberData->content,
            'campaign'     => $numberData->campaign,
            'phone_number_config_id' => $config->id,
            'country'      => $company->country,
            'country_code' => PhoneNumber::countryCode($twilioNumber->phoneNumber),
            'number'       => PhoneNumber::number($twilioNumber->phoneNumber),
            'swap_rules'   => null,
            'call_count'   => 0,
            'link'         => route('read-phone-number', [
                'company' => $company->id,
                'phoneNumber' => $response['id']
            ]),
            'kind'             => 'PhoneNumber',
        ]);

        $this->assertDatabaseHas('phone_numbers', [
            'company_id' => $company->id,
            'account_id' => $company->account_id,
            'id' => $response['id']
        ]);
    }

    /**
     * Test creating an online email number. When not WEBSITE, swap rules should be null.
     * 
     * @group phone-numbers
     */
    public function testCreateOnlineEmailPhoneNumber()
    {
        $company    = $this->createCompany();
        $config     = $this->createConfig($company);

        $numberData = factory(PhoneNumber::class)->make([
            'category'     => 'ONLINE',
            'sub_category' => 'EMAIL'
        ]);

        $areaCode   = '813'; 
        if( $numberData->type === PhoneNumber::TYPE_TOLL_FREE )
            $areaCode   = ''; 
        
        $twilioNumber = factory('Tests\Models\TwilioPhoneNumber')->make();

        $this->mock(PhoneNumberService::class, function ($mock) use($numberData, $areaCode, $company, $twilioNumber){
            $mock->shouldReceive('listAvailable')
                 ->once()
                 ->with($areaCode, 1, $numberData->type, $company->country)
                 ->andReturn(
                    [$twilioNumber]
                );

            $mock->shouldReceive('purchase')
                ->once()
                ->with($twilioNumber->phoneNumber)
                ->andReturn(
                   $twilioNumber
               );
        });

        $response = $this->json('POST', route('create-phone-number', [
            'company' => $company->id
        ]), [
            'name'        => $numberData->name,
            'category'    => $numberData->category,
            'sub_category'=> $numberData->sub_category,
            'type'        => $numberData->type,
            'starts_with' => $areaCode,
            'source'      => $numberData->source,
            'medium'      => $numberData->medium,
            'content'     => $numberData->content,
            'campaign'    => $numberData->campaign,
            'phone_number_config_id' => $config->id,
            'swap_rules'  => json_encode($numberData->swap_rules)
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'account_id'  => $company->account_id,
            'company_id'  => $company->id,
            'name'        => $numberData->name,
            'category'    => $numberData->category,
            'sub_category'=> $numberData->sub_category,
            'type'        => $numberData->type,
            'source'      => $numberData->source,
            'medium'      => $numberData->medium,
            'content'     => $numberData->content,
            'campaign'     => $numberData->campaign,
            'phone_number_config_id' => $config->id,
            'country'      => $company->country,
            'country_code' => PhoneNumber::countryCode($twilioNumber->phoneNumber),
            'number'       => PhoneNumber::number($twilioNumber->phoneNumber),
            'swap_rules'   => null,
            'call_count'   => 0,
            'link'         => route('read-phone-number', [
                'company' => $company->id,
                'phoneNumber' => $response['id']
            ]),
            'kind'             => 'PhoneNumber',
        ]);

        $this->assertDatabaseHas('phone_numbers', [
            'company_id' => $company->id,
            'account_id' => $company->account_id,
            'id' => $response['id']
        ]);
    }

    /**
     * Test creating an online website. When WEBSITE, swap rules should not be null.
     * 
     * @group phone-numbers
     */
    public function testCreateOnlineWebsitePhoneNumber()
    {
        $company    = $this->createCompany();
        $config     = $this->createConfig($company);

        $numberData = factory(PhoneNumber::class)->make([
            'category'     => 'ONLINE',
            'sub_category' => 'WEBSITE'
        ]);

        $areaCode   = '813'; 
        if( $numberData->type === PhoneNumber::TYPE_TOLL_FREE )
            $areaCode   = ''; 
        
        $twilioNumber = factory('Tests\Models\TwilioPhoneNumber')->make();

        $this->mock(PhoneNumberService::class, function ($mock) use($numberData, $areaCode, $company, $twilioNumber){
            $mock->shouldReceive('listAvailable')
                 ->once()
                 ->with($areaCode, 1, $numberData->type, $company->country)
                 ->andReturn(
                    [$twilioNumber]
                );

            $mock->shouldReceive('purchase')
                ->once()
                ->with($twilioNumber->phoneNumber)
                ->andReturn(
                   $twilioNumber
               );
        });

        //  Make sure it fails at first for not providing swal rules
        $response = $this->json('POST', route('create-phone-number', [
            'company' => $company->id
        ]), [
            'name'        => $numberData->name,
            'category'    => $numberData->category,
            'sub_category'=> $numberData->sub_category,
            'type'        => $numberData->type,
            'starts_with' => $areaCode,
            'source'      => $numberData->source,
            'medium'      => $numberData->medium,
            'content'     => $numberData->content,
            'campaign'    => $numberData->campaign,
            'phone_number_config_id' => $config->id,
        ]);
        $response->assertStatus(400);
        $response->assertJSONStructure([
            'error'
        ]);

        //  Then passes with swap rules
        $response = $this->json('POST', route('create-phone-number', [
            'company' => $company->id
        ]), [
            'name'        => $numberData->name,
            'category'    => $numberData->category,
            'sub_category'=> $numberData->sub_category,
            'type'        => $numberData->type,
            'starts_with' => $areaCode,
            'source'      => $numberData->source,
            'medium'      => $numberData->medium,
            'content'     => $numberData->content,
            'campaign'    => $numberData->campaign,
            'phone_number_config_id' => $config->id,
            'swap_rules'  => json_encode($numberData->swap_rules)
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'account_id'  => $company->account_id,
            'company_id'  => $company->id,
            'name'        => $numberData->name,
            'category'    => $numberData->category,
            'sub_category'=> $numberData->sub_category,
            'type'        => $numberData->type,
            'source'      => $numberData->source,
            'medium'      => $numberData->medium,
            'content'     => $numberData->content,
            'campaign'     => $numberData->campaign,
            'phone_number_config_id' => $config->id,
            'country'      => $company->country,
            'country_code' => PhoneNumber::countryCode($twilioNumber->phoneNumber),
            'number'       => PhoneNumber::number($twilioNumber->phoneNumber),
            'swap_rules'   => json_decode(json_encode($numberData->swap_rules), true),
            'call_count'   => 0,
            'link'         => route('read-phone-number', [
                'company' => $company->id,
                'phoneNumber' => $response['id']
            ]),
            'kind'             => 'PhoneNumber',
        ]);

        $this->assertDatabaseHas('phone_numbers', [
            'company_id' => $company->id,
            'account_id' => $company->account_id,
            'id' => $response['id']
        ]);
    }

    /**
     * Test creating all offline types
     * 
     * @group phone-numbers
     */
    public function testCreateAllOfflinePhoneNumberTypes()
    {
        $company       = $this->createCompany();
        $config        = $this->createConfig($company);
        $paymentMethod = $this->createPaymentMethod();

        $numberData = factory(PhoneNumber::class)->make([
            'category' => 'OFFLINE'
        ]);
        $areaCode   = '813'; 
        if( $numberData->type === PhoneNumber::TYPE_TOLL_FREE )
            $areaCode   = ''; 
        
        $twilioNumber = factory('Tests\Models\TwilioPhoneNumber')->make();
        $this->mock(PhoneNumberService::class, function ($mock) use($numberData, $areaCode, $company, $twilioNumber){
            $mock->shouldReceive('listAvailable')
                 ->with($areaCode, 1, $numberData->type, $company->country)
                 ->times(count(PhoneNumber::OFFLINE_SUB_CATEGORIES))
                 ->andReturn(
                    [$twilioNumber]
                );

            $mock->shouldReceive('purchase')
                ->times(count(PhoneNumber::OFFLINE_SUB_CATEGORIES))
                ->with($twilioNumber->phoneNumber)
                ->andReturn(
                   $twilioNumber
               );
        });

        foreach( PhoneNumber::OFFLINE_SUB_CATEGORIES as $subCategory ){
            $response = $this->json('POST', route('create-phone-number', [
                'company' => $company->id
            ]), [
                'name'        => $numberData->name,
                'category'    => $numberData->category,
                'sub_category'=> $subCategory,
                'type'        => $numberData->type,
                'starts_with' => $areaCode,
                'source'      => $numberData->source,
                'medium'      => $numberData->medium,
                'content'     => $numberData->content,
                'campaign'    => $numberData->campaign,
                'phone_number_config_id' => $config->id,
                'swap_rules'  => json_encode($numberData->swap_rules)
            ]);
            $response->assertStatus(201);
            $response->assertJSON([
                'account_id'  => $company->account_id,
                'company_id'  => $company->id,
                'name'        => $numberData->name,
                'category'    => $numberData->category,
                'sub_category'=> $subCategory,
                'type'        => $numberData->type,
                'source'      => $numberData->source,
                'medium'      => $numberData->medium,
                'content'     => $numberData->content,
                'campaign'     => $numberData->campaign,
                'phone_number_config_id' => $config->id,
                'country'      => $company->country,
                'country_code' => PhoneNumber::countryCode($twilioNumber->phoneNumber),
                'number'       => PhoneNumber::number($twilioNumber->phoneNumber),
                'swap_rules'   => null,
                'call_count'   => 0,
                'link'         => route('read-phone-number', [
                    'company' => $company->id,
                    'phoneNumber' => $response['id']
                ]),
                'kind'             => 'PhoneNumber',
            ]);

            $this->assertDatabaseHas('phone_numbers', [
                'company_id' => $company->id,
                'account_id' => $company->account_id,
                'id' => $response['id']
            ]);
        }
    }

    /*
     |-------------
     | Update
     |------------- 
     |
     | 
     */

    /**
     * Test disabling a phone number
     * 
     * @group phone-numbers
     */
    public function testDisableEnablePhoneNumber()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $phoneNumber = $this->createPhoneNumber($company, $config, [
            'disabled_at' => null
        ]); 
        $expectedData = json_decode(json_encode($phoneNumber->toArray()), true);
        unset($expectedData['disabled_at']); // Disabled time may differ by seconds from now
        unset($expectedData['updated_at']);
        
        //  Disable
        $response = $this->json('PUT', route('update-phone-number', [
            'company'     => $company->id,
            'phoneNumber' => $phoneNumber->id
        ]), [
            'disabled' => 1
        ]);
        $response->assertStatus(200);
        $response->assertJSON($expectedData);
        $this->assertNotNull($response['disabled_at']);

        //  Re-enable
        $response = $this->json('PUT', route('update-phone-number', [
            'company'     => $company->id,
            'phoneNumber' => $phoneNumber->id
        ]), [
            'disabled' => 0
        ]);

        $response->assertStatus(200);
        $response->assertJSON($expectedData);
        $this->assertNull($response['disabled_at']);
    }

    /**
     * Test updating a phone number
     * 
     * @group phone-numbers
     */
    public function testUpdatePhoneNumber()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $phoneNumber = $this->createPhoneNumber($company, $config, [
            'disabled_at' => null
        ]); 

        $newConfig         =  $this->createConfig($company);
        $updatedNumberData = factory(PhoneNumber::class)->make();

        $response = $this->json('PUT', route('update-phone-number', [
            'company'     => $company->id,
            'phoneNumber' => $phoneNumber->id
        ]), [
            'name'                   => $updatedNumberData->name,
            'source'                 => $updatedNumberData->source,  
            'medium'                 => $updatedNumberData->medium,  
            'content'                => $updatedNumberData->content,  
            'campaign'               => $updatedNumberData->campaign,        
            'phone_number_config_id' => $newConfig->id,
            'category'               => $updatedNumberData->category,
            'sub_category'           => $updatedNumberData->sub_category,
            'swap_rules'             => json_encode($updatedNumberData->swap_rules)
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'phone_number_config_id' => $newConfig->id,
            'name'                   => $updatedNumberData->name,
            'source'                 => $updatedNumberData->source,  
            'medium'                 => $updatedNumberData->medium,  
            'content'                => $updatedNumberData->content,  
            'campaign'               => $updatedNumberData->campaign,        
            'swap_rules'             => $updatedNumberData->sub_category == 'WEBSITE' ? json_decode(json_encode($updatedNumberData->swap_rules), true) : null,
        ]);

        $this->assertDatabaseHas('phone_numbers', [
            'phone_number_config_id' => $newConfig->id,
            'name'                   => $updatedNumberData->name,
            'source'                 => $updatedNumberData->source,  
            'medium'                 => $updatedNumberData->medium,  
            'content'                => $updatedNumberData->content,  
            'campaign'               => $updatedNumberData->campaign
        ]);
    }

    /**
     * Test reading a phone number
     * 
     * @group phone-numbers
     */
    public function testReadPhoneNumber()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $phoneNumber = $this->createPhoneNumber($company, $config); 

        $response = $this->json('GET', route('read-phone-number', [
            'company'     => $company->id,
            'phoneNumber' => $phoneNumber->id
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            'account_id'  => $phoneNumber->account_id,
            'company_id'  => $phoneNumber->company_id,
            'name'        => $phoneNumber->name,
            'category'    => $phoneNumber->category,
            'sub_category'=> $phoneNumber->sub_category,
            'type'        => $phoneNumber->type,
            'source'      => $phoneNumber->source,
            'medium'      => $phoneNumber->medium,
            'content'     => $phoneNumber->content,
            'campaign'     => $phoneNumber->campaign,
            'phone_number_config_id' => $phoneNumber->phone_number_config_id,
            'country'      => $phoneNumber->country,
            'country_code' => $phoneNumber->country_code,
            'number'       => $phoneNumber->number,
            'call_count'   => $phoneNumber->call_count,
            'link'         => $phoneNumber->link,
            'kind'         => $phoneNumber->kind,
        ]);
    }

    /**
     * Test deleting a phone number will release it
     * 
     * @group phone-numbers
     */
    public function testDeletePhoneNumberIsReleased()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $phoneNumber = $this->createPhoneNumber($company, $config); 

        $this->mock(PhoneNumberService::class, function ($mock) use($phoneNumber){
            $mock->shouldReceive('releaseNumber')
                 ->once()
                 ->with(PhoneNumber::class);
        });

        $response = $this->json('DELETE', route('delete-phone-number', [
            'company'     => $company->id,
            'phoneNumber' => $phoneNumber->id
        ]));
    }

    /**
     * Test twilio client function numbers
     * 
     * @group phone-numbers
     */
    public function testTwilioListsNumbersWithPurchase()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);

        $twilioNumber = factory('Tests\Models\TwilioPhoneNumber')->make();

        $mock = $this->partialMock(PhoneNumberService::class);
        $mock->client = $this->partialMock(Twilio::class, function($mock) use($twilioNumber){
            $query = $this->mock('stdClass');
            $query->local = $this->mock('stdClass', function($m) use($twilioNumber){
                $m->shouldReceive('read')->once()->andReturn([$twilioNumber]);
            });

            $mock->shouldReceive('availablePhoneNumbers')
                ->once()
                ->with('US')
                ->andReturn($query);

            $mock->incomingPhoneNumbers = $this->mock('stdClass', function($mock) use($twilioNumber){
                    $mock->shouldReceive('create')
                        ->once()
                        ->andReturn($twilioNumber);
            });
                
        });


        $numberData = factory(PhoneNumber::class)->make();
        $areaCode   = '813'; 

        $response = $this->json('POST', route('create-phone-number', [
            'company' => $company->id
        ]), [
            'name'        => $numberData->name,
            'category'    => $numberData->category,
            'sub_category'=> $numberData->sub_category,
            'type'        => PhoneNumber::TYPE_LOCAL,
            'starts_with' => $areaCode,
            'source'      => $numberData->source,
            'medium'      => $numberData->medium,
            'content'     => $numberData->content,
            'campaign'    => $numberData->campaign,
            'phone_number_config_id' => $config->id,
            'swap_rules'  => json_encode($numberData->swap_rules)
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'name'        => $numberData->name,
            'country_code'=> PhoneNumber::countryCode($twilioNumber->phoneNumber),
            'number'      => PhoneNumber::number($twilioNumber->phoneNumber),
            'source'      => $numberData->source,
            'medium'      => $numberData->medium,
            'content'     => $numberData->content,
            'campaign'    => $numberData->campaign,
            'kind' => 'PhoneNumber'
        ]);
    }
}
