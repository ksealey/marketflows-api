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
                'created_at'      => today()->subDays(9)->format('Y-m-d') . ' ' . $faker->time()
            ]);
        });

        $response = $this->json('GET', route('report-total-calls', [
            'company' => $company->id
        ]), [
            'start_date' => today()->subDays(7),
            'end_date'   => today(),
            'date_type'  => 'CUSTOM'
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'kind'  => 'Report',
            'url'   => route('report-total-calls', [ 
                'company' => $company->id
            ]),
            'data'  => [
                'type'     => 'line',
                'title'    => 'Calls',
                'datasets' => [
                    [
                        'total' => 8
                    ]
                ]
            ]
        ]);

        //
        //  Try again with range that covers all calls
        //
        $response = $this->json('GET', route('report-total-calls', [
            'company' => $company->id
        ]), [
            'date_type' => 'ALL_TIME' 
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'kind'  => 'Report',
            'url'   => route('report-total-calls', [ 
                'company' => $company->id
            ]),
            'data'  => [
                'type'     => 'line',
                'title'    => 'Calls',
                'datasets' => [
                    [
                        'total' => 16
                    ]
                ]
            ]
        ]);
    }

    /**
     * Test call sources report
     * 
     * @group reports
     */
    public function testCallSourcesReport()
    {
        $company        = $this->createCompany();
        $config         = $this->createConfig($company);
        $phoneNumber    = $this->createPhoneNumber($company, $config);
        $sources        = [str_random(10), str_random(10), str_random(10), str_random(10)]; 
        $mediums        = [str_random(10), str_random(10), str_random(10), str_random(10)]; 
        $campaigns      = [str_random(10), str_random(10), str_random(10), str_random(10)]; 
        $contents       = [str_random(10), str_random(10), str_random(10), str_random(10)]; 

        factory(Contact::class, 4)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id
        ])->each(function($contact, $i) use($phoneNumber, $sources, $mediums, $campaigns, $contents){
            //  Create two in date range
            $faker = $this->faker();
            
            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'created_at'      => today()->subDays(7)->format('Y-m-d') . ' ' . $faker->time(),
                'source'          => $sources[$i],
                'medium'          => $mediums[$i],
                'campaign'        => $campaigns[$i],
                'content'         => $sources[$i]
            ]);

            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'created_at'      => today()->format('Y-m-d') . ' ' . $faker->time(),
                'source'          => $sources[$i],
                'medium'          => $mediums[$i],
                'campaign'        => $campaigns[$i],
                'content'         => $sources[$i]
            ]);

            //  Create one out date range
            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'created_at'      => today()->subDays(8)->format('Y-m-d') . ' ' . $faker->time(),
                'source'          => $sources[$i],
                'medium'          => $mediums[$i],
                'campaign'        => $campaigns[$i],
                'content'         => $sources[$i]
            ]);
        });

        $response = $this->json('GET', route('report-call-sources', [
            'company' => $company->id,
            'group_by' => 'source'
        ]), [
            'start_date' => today()->subDays(7),
            'end_date'   => today(),
            'date_type'  => 'CUSTOM',
            'vs_previous_period' => 1
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'kind'  => 'Report',
            'url'   => route('report-call-sources', [ 
                'company' => $company->id
            ]),
            'data'  => [
                'type'     => 'bar',
                'datasets' => [
                    [ 'total' => 8 ],
                    [ 'total' => 4 ]
                ],
            ]
        ]);

        //
        //  Try again with range that covers all calls
        //
        $response = $this->json('GET', route('report-call-sources', [
            'company' => $company->id,
            'group_by' => 'source'
        ]), [
            'start_date' => today()->subDays(7),
            'end_date'   => today(),
            'date_type'  => 'ALL_TIME',
            'vs_previous_period' => 1
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'kind'  => 'Report',
            'url'   => route('report-call-sources', [ 
                'company' => $company->id
            ]),
            'data'  => [
                'type'     => 'bar',
                'datasets' => [
                    [
                        'total' => 12
                    ],
                    [
                        'total' => 0
                    ]
                ],
                
            ]
        ]);
    }
}
