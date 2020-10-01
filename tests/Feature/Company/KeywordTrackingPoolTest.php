<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use \App\Models\Company\PhoneNumber;
use App\Models\Company\KeywordTrackingPool;
use App\Services\PhoneNumberService;
use Tests\Models\TwilioPhoneNumber;
use App\Jobs\DeleteKeywordTrackingPoolJob;
use Queue;

class KeywordTrackingPoolTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test listing
     * 
     * @group keyword-tracking-pools
     */
    public function testList()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = factory(KeywordTrackingPool::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by' => $this->user->id
        ]);

        $phoneNumbers = factory(PhoneNumber::class, 10)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'keyword_tracking_pool_id' => $pool->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('GET', route('list-keyword-tracking-pools', [
            'company' => $company->id
        ]));

        $response->assertJSON([
            "result_count"  => 1,
            "limit"         => 250,
            "page"          => 1,
            "total_pages"   => 1,
            "next_page"     => null,
            "results"       => [
                [
                    'account_id'                => $company->account_id,
                    'company_id'                => $company->id,
                    'id'                        => $pool->id,
                    'name'                      => $pool->name,
                    'phone_number_config_id'    => $pool->phone_number_config_id,
                    'swap_rules'                => json_decode(json_encode($pool->swap_rules), true),
                ]
            ]    
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test create local phone number pool
     * 
     * @group keyword-tracking-pools 
     */
    public function testCreateLocal()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = factory(KeywordTrackingPool::class)->make();

        $poolSize      = mt_rand(5, 20);
        $startsWith    = strval(mt_rand(111,999));
        $twilioNumbers = factory(TwilioPhoneNumber::class, $poolSize)->make();

        $this->mock(PhoneNumberService::class, function($mock) use($company, $twilioNumbers, $poolSize, $startsWith){
            $mock->shouldReceive('listAvailable')
                 ->with($startsWith, $poolSize, PhoneNumber::TYPE_LOCAL, $company->country)
                 ->andReturn($twilioNumbers)
                 ->once();
        
            $twilioNumbers->each(function($twilioNumber) use($mock){
                $mock->shouldReceive('purchase')
                     ->with($twilioNumber->phoneNumber)
                     ->andReturn($twilioNumber);
            });
        });

        $response = $this->json('POST', route('create-keyword-tracking-pool', [
            'company' => $company->id
        ]),[
            'name'                   => $pool->name,
            'phone_number_config_id' => $config->id,
            'type'                   => PhoneNumber::TYPE_LOCAL,
            'starts_with'            => $startsWith,
            'swap_rules'             => json_encode($pool->swap_rules),
            'pool_size'              => $poolSize        
        ]);

        $response->assertJSON([
            'kind'                   => 'KeywordTrackingPool',
            'name'                   => $pool->name,
            'phone_number_config_id' => $config->id,
            'phone_numbers'          => []
        ]);

        $response->assertStatus(201);

        foreach( $response['phone_numbers'] as $phoneNumber ){
            $this->assertEquals($phoneNumber['type'], PhoneNumber::TYPE_LOCAL);
            $this->assertDatabaseHas('phone_numbers', [
                'id'                       => $phoneNumber['id'],
                'keyword_tracking_pool_id' => $response['id'],
                'deleted_at' => null
            ]);
        }
    }

    /**
     * Test create toll free phone nnumber pool
     * 
     * @group keyword-tracking-pools 
     */
    public function testCreateTollFree()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = factory(KeywordTrackingPool::class)->make();

        $poolSize      = mt_rand(5, 20);
        $startsWith    = strval(mt_rand(111,999));
        $twilioNumbers = factory(TwilioPhoneNumber::class, $poolSize)->make();

        $this->mock(PhoneNumberService::class, function($mock) use($company, $twilioNumbers, $poolSize){
            $mock->shouldReceive('listAvailable')
                 ->with('', $poolSize, PhoneNumber::TYPE_TOLL_FREE, $company->country)
                 ->andReturn($twilioNumbers)
                 ->once();
        
            $twilioNumbers->each(function($twilioNumber) use($mock){
                $mock->shouldReceive('purchase')
                     ->with($twilioNumber->phoneNumber)
                     ->andReturn($twilioNumber);
            });
        });

        $response = $this->json('POST', route('create-keyword-tracking-pool', [
            'company' => $company->id
        ]),[
            'name'                   => $pool->name,
            'phone_number_config_id' => $config->id,
            'type'                   => PhoneNumber::TYPE_TOLL_FREE,
            'swap_rules'             => json_encode($pool->swap_rules),
            'pool_size'              => $poolSize        
        ]);

        $response->assertJSON([
            'kind'                   => 'KeywordTrackingPool',
            'name'                   => $pool->name,
            'phone_number_config_id' => $config->id,
            'phone_numbers'          => []
        ]);

        $response->assertStatus(201);

        foreach( $response['phone_numbers'] as $phoneNumber ){
            $this->assertEquals($phoneNumber['type'], PhoneNumber::TYPE_TOLL_FREE);
            $this->assertDatabaseHas('phone_numbers', [
                'id'                       => $phoneNumber['id'],
                'keyword_tracking_pool_id' => $response['id'],
                'deleted_at' => null
            ]);
        }
    }

    /**
     * Test read
     * 
     * @group keyword-tracking-pools
     */
    public function testRead()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = factory(KeywordTrackingPool::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by' => $this->user->id
        ]);

        $phoneNumbers = factory(PhoneNumber::class, 10)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'keyword_tracking_pool_id' => $pool->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('GET', route('read-keyword-tracking-pool', [
            'company'             => $company->id,
            'keywordTrackingPool' => $pool->id
        ]));
        $response->assertJSON([
            'account_id'                => $company->account_id,
            'company_id'                => $company->id,
            'id'                        => $pool->id,
            'phone_number_config_id'    => $pool->phone_number_config_id,
            'swap_rules'                => json_decode(json_encode($pool->swap_rules), true)   
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test update
     * 
     * @group keyword-tracking-pools
     */
    public function testUpdate()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = factory(KeywordTrackingPool::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by' => $this->user->id
        ]);
    
        $phoneNumbers = factory(PhoneNumber::class, 10)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'keyword_tracking_pool_id' => $pool->id,
            'created_by' => $this->user->id
        ]);

        $updateData   = factory(KeywordTrackingPool::class)->make([
            'phone_number_config_id' => $this->createConfig($company)->id
        ]);

        $response = $this->json('PUT', route('update-keyword-tracking-pool', [
            'company'             => $company->id,
            'keywordTrackingPool' => $pool->id
        ]), [
            'name'                   => $updateData->name,
            'phone_number_config_id' => $updateData->phone_number_config_id,
            'swap_rules'             => json_encode($updateData->swap_rules),
        ]);
        
        $response->assertJSON([
            'account_id'                => $company->account_id,
            'company_id'                => $company->id,
            'id'                        => $pool->id,
            'name'                      => $updateData->name,
            'phone_number_config_id'    => $updateData->phone_number_config_id,
            'swap_rules'                => json_decode(json_encode($updateData->swap_rules), true)   
        ]);

        $response->assertStatus(200);

        foreach( $response['phone_numbers'] as $phoneNumber ){
            $this->assertEquals($phoneNumber['swap_rules'], json_decode(json_encode($updateData->swap_rules), true));
            $this->assertDatabaseHas('phone_numbers', [
                'id'                       => $phoneNumber['id'],
                'keyword_tracking_pool_id' => $response['id'],
                'phone_number_config_id'   => $updateData->phone_number_config_id,
                'deleted_at'               => null
            ]);
        }
    }

    /**
     * Test delete
     * 
     * @group keyword-tracking-pools
     */
    public function testDelete()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = factory(KeywordTrackingPool::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by' => $this->user->id
        ]);

        $phoneNumbers = factory(PhoneNumber::class, mt_rand(5,20))->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'keyword_tracking_pool_id' => $pool->id,
            'created_by' => $this->user->id
        ]);

        $this->mock(PhoneNumberService::class, function($mock) use($phoneNumbers){
            $mock->shouldReceive('releaseNumber')
                ->with(PhoneNumber::class)
                ->times(count($phoneNumbers));
        });

        $response = $this->json('DELETE', route('delete-keyword-tracking-pool', [
            'company'             => $company->id,
            'keywordTrackingPool' => $pool->id
        ]), [
            'release_numbers' => 1
        ]);
        
        $response->assertJSON([
            'message' => 'Delete queued'  
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('keyword_tracking_pools',[
            'id'         => $pool->id,
            'deleted_by' => $this->user->id
        ]);

        $phoneNumbers->each(function($phoneNumber){
            $this->assertDatabaseHas('phone_numbers', [
                'id'     => $phoneNumber->id,
                'deleted_by' => $this->user->id,
                'keyword_tracking_pool_id' => null
            ]);
        });
    }

    /**
     * Test delete without release
     * 
     * @group keyword-tracking-pools
     */
    public function testDeleteWithoutRelease()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = factory(KeywordTrackingPool::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by' => $this->user->id
        ]);

        $phoneNumbers = factory(PhoneNumber::class, mt_rand(5,20))->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'keyword_tracking_pool_id' => $pool->id,
            'created_by' => $this->user->id
        ]);

        $this->mock(PhoneNumberService::class, function($mock) use($phoneNumbers){
            $mock->shouldNotReceive('releaseNumber');
        });

        $response = $this->json('DELETE', route('delete-keyword-tracking-pool', [
            'company'             => $company->id,
            'keywordTrackingPool' => $pool->id
        ]));
        
        $response->assertJSON([
            'message' => 'Delete queued'  
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('keyword_tracking_pools',[
            'id'         => $pool->id,
            'deleted_by' => $this->user->id
        ]);

        $phoneNumbers->each(function($phoneNumber){
            $this->assertDatabaseHas('phone_numbers', [
                'id'                        => $phoneNumber->id,
                'deleted_by'                => null,
                'deleted_at'                => null,
                'keyword_tracking_pool_id'  => null
            ]);
        });
    }
}
