<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use \App\Models\Company;
use \App\Models\Company\PhoneNumberConfig;
use \App\Models\Company\PhoneNumberPool;
use \App\Models\Company\PhoneNumber;

class PhoneNumberPoolTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test listing a phone number pool
     * 
     * @group phone-number-pools
     */
    public function testListingPhoneNumberPools()
    {
        $company = $this->createCompany();

        $config = factory(PhoneNumberConfig::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $pool = factory(PhoneNumberPool::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'phone_number_config_id' =>  $config->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('GET', route('list-phone-number-pools', [
            'company' => $company->id
        ]));
        
        $response->assertStatus(200);
        $response->assertJSON([
            "result_count" => 1,
            "limit" => 250,
            "page" => 1,
            "total_pages" => 1,
            "next_page" => null
        ]);
    }
}
