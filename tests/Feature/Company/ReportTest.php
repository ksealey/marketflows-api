<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use \App\Models\Company\Report;
use \App\Models\Company\ReportAutomation;
use \App\Models\Company\Contact;
use \App\Models\Company\Call;
use \App\Jobs\ExportResultsJob;
use \DateTimeZone;
use Queue;

class ReportTest extends TestCase
{
    use \Tests\CreatesAccount, WithFaker;

    /**
     * Test viewing total call report
     * 
     * @group reports
     */
    public function testReportTotalCalls()
    {    
        $company        = $this->createCompany();
        $config         = $this->createConfig($company);
        $phoneNumber    = $this->createPhoneNumber($company, $config);

        factory(Contact::class, 4)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id
        ])->each(function($contact) use($phoneNumber){
            //  Create two in date range
            $faker = $this->faker();
            
            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'created_at'      => today()->subDays(7)->format('Y-m-d') . ' ' . $faker->time()
            ]);

            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'created_at'      => today()->format('Y-m-d') . ' ' . $faker->time()
            ]);

            //  Create two out date range
            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'created_at'      => today()->subDays(8)->format('Y-m-d') . ' ' . $faker->time()
            ]);

            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'created_at'      => today()->addDays(1)->format('Y-m-d') . ' ' . $faker->time()
            ]);
        });

        $response = $this->json('GET', route('report-total-calls', [
            'company' => $company->id
        ]), [
            'start_date' => today()->subDays(7),
            'end_date'   => today()
        ]);
        
        $response->assertStatus(200);
        $response->assertJSON([
            'kind'      => 'Report',
            'data_type' => 'count',
            'data' => [
                'count' => 8
            ]
        ]);

        //
        //  Try again with range that covers all calls
        //
        $response = $this->json('GET', route('report-total-calls', [
            'company' => $company->id
        ]), [
            'start_date' => today()->subDays(8),
            'end_date'   => today()->addDays(1)
        ]);
        
        $response->assertStatus(200);
        $response->assertJSON([
            'kind'      => 'Report',
            'data_type' => 'count',
            'data' => [
                'count' => 16
            ]
        ]);
    }
}
