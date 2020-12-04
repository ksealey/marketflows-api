<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\BlockedPhoneNumber;
use App\Models\BlockedCall;
use App\Services\ExportService;

class BlockedCallTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test listing blocked calls
     *
     * @group blocked-calls
     */
    public function testList()
    {
        $company        = $this->createCompany();
        $config         = $this->createConfig($company);
        $phoneNumber    = $this->createPhoneNumber($company, $config);
        $blockedPhoneNumber = factory(BlockedPhoneNumber::class)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id
        ]);

        $blockedCalls = factory(BlockedCall::class, 10)->create([
            'blocked_phone_number_id' => $blockedPhoneNumber->id,
            'phone_number_id'=> $phoneNumber->id,
            'account_id' => $this->account->id
        ]);

        $response = $this->json('GET', route('list-blocked-calls'));
        $response->assertJSON([
            "results"       => [
                [
                    'phone_number_id' => $phoneNumber->id,
                    'blocked_phone_number_id' => $blockedPhoneNumber->id
                ]
            ],
            "result_count"  => 10,
            "limit"         => 250,
            "page"          => 1,
            "total_pages"   => 1,
            "next_page"     => null
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test listing blocked calls with single condition
     *
     * @group blocked-calls
     */
    public function testListWithSingleConditions()
    {
        $company        = $this->createCompany();
        $config         = $this->createConfig($company);
        $phoneNumber    = $this->createPhoneNumber($company, $config);
        $blockedPhoneNumber = factory(BlockedPhoneNumber::class)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id
        ]);

        $blockedCalls = factory(BlockedCall::class, 10)->create([
            'blocked_phone_number_id'   => $blockedPhoneNumber->id,
            'phone_number_id'           => $phoneNumber->id,
            'account_id'                => $this->account->id
        ]);

        $firstRecord = $blockedCalls->first();

        $response = $this->json('GET', route('list-blocked-calls'), [
            'conditions' => json_encode([
                [
                    [
                        'field'     => 'blocked_calls.id',
                        'operator'  => 'EQUALS',
                        'inputs'    => [
                            $firstRecord->id
                        ]
                    ]
                ]
            ])
        ]);

        $response->assertJSON([
            "results"       => [
                [
                    'id'                        => $firstRecord->id,
                    'phone_number_id'           => $phoneNumber->id,
                    'blocked_phone_number_id'   => $blockedPhoneNumber->id
                ]
            ],
            "result_count"  => 1,
            "limit"         => 250,
            "page"          => 1,
            "total_pages"   => 1,
            "next_page"     => null
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test exporting blocked calls
     * 
     * @group blocked-calls
     */
    public function testExportBlockedPhoneNumbers()
    {
        $company        = $this->createCompany();
        $config         = $this->createConfig($company);
        $phoneNumber    = $this->createPhoneNumber($company, $config);
        $blockedPhoneNumber = factory(BlockedPhoneNumber::class)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id
        ]);

        $blockedCalls = factory(BlockedCall::class, 10)->create([
            'blocked_phone_number_id' => $blockedPhoneNumber->id,
            'phone_number_id'=> $phoneNumber->id,
            'account_id' => $this->account->id
        ]);

        $exportData  = bin2hex(random_bytes(200));
        $this->mock(ExportService::class, function($mock) use($exportData){
            $mock->shouldReceive('exportAsOutput')
                 ->once()
                 ->andReturn($exportData);
        });
        
        $response = $this->json('GET', route('export-blocked-calls'));
        
        $response->assertStatus(200);
        $response->assertSee($exportData);
            
    }
}
