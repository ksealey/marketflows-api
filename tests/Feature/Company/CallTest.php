<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Company\Contact;
use App\Models\Company\Call;
use App\Services\ExportService;

class CallTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test listing calls
     *
     * @group calls
     */
    public function testList()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $phoneNumber = $this->createPhoneNumber($company, $config);

        factory(Contact::class, 15)->create([
            'account_id'             => $company->account_id,
            'company_id'             => $company->id,
        ])->each(function($contact) use($phoneNumber){
            factory(Call::class)->create([
                'account_id'             => $contact->account_id,
                'company_id'             => $contact->company_id,
                'contact_id'             => $contact->id,
                'phone_number_id'        => $phoneNumber->id,
                'phone_number_name'      => $phoneNumber->name
            ]);
        });
        
        $response = $this->json('GET', route('list-calls', [
            'company' => $company->id
        ]));
        $response->assertStatus(200);
        $response->assertJSON([
            "results" => [],
            "result_count" => 15,
            "limit" => 250,
            "page" => 1,
            "total_pages" => 1,
            "next_page" => null
        ]);
    }

     /**
     * Test exporting calls
     *
     * @group calls
     */
    public function testExport()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $phoneNumber = $this->createPhoneNumber($company, $config);

        factory(Contact::class, 15)->create([
            'account_id'             => $company->account_id,
            'company_id'             => $company->id,
        ])->each(function($contact) use($phoneNumber){
            factory(Call::class)->create([
                'account_id'             => $contact->account_id,
                'company_id'             => $contact->company_id,
                'contact_id'             => $contact->id,
                'phone_number_id'        => $phoneNumber->id,
                'phone_number_name'      => $phoneNumber->name
            ]);
        });
        
        $exportData  = bin2hex(random_bytes(200));
        $this->partialMock(ExportService::class, function($mock) use($exportData){
            $mock->shouldReceive('save')
                 ->andReturn($exportData)
                 ->once();
        });
        $response = $this->json('GET', route('export-calls', [
            'company' => $company->id
        ]));
        $response->assertStatus(200);
        $response->assertSee($exportData);
    }
}
