<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use \App\Models\Company\Contact;
use \App\Models\Company\Report;
use \App\Models\Company\ScheduledExport;
use \App\Models\Company\Call;
use \App\Services\ReportService;
use \DateTimeZone;
use Queue;
use App;

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
        $company        = $this->createCompany([
            'created_at' => now()->subDays(50)
        ]);
        $config         = $this->createConfig($company);
        $phoneNumber    = $this->createPhoneNumber($company, $config);

        factory(Contact::class, 4)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id
        ])->each(function($contact) use($phoneNumber){
            //  Create two in date range
            factory(Call::class)->create([
                'account_id'        => $contact->account_id,
                'company_id'        => $contact->company_id,
                'contact_id'        => $contact->id,
                'phone_number_id'   => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'        => now()->subDays(2)
            ]);

            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(3)
            ]);

            //  Create two out of date
           factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(9)
            ]);

            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(10)
            ]);
        });
        

        $response = $this->json('GET', route('report-total-calls', [
            'company' => $company->id
        ]), [
            'start_date' => today()->setTimeZone($this->user->timezone)->subDays(7),
            'end_date'   => today()->setTimeZone($this->user->timezone),
            'date_type'  => 'CUSTOM',
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'kind'  => 'Report',
            'link'  => route('report-total-calls', [ 
                'company' => $company->id
            ]),
            'data'  => [
                'type'     => 'timeframe',
                'title'    => 'Calls',
                'datasets' => [
                    [
                        'total' => 8
                    ],
                ]
            ]
        ]);

        //
        //  Try again with range that covers all calls
        //
        $response = $this->json('GET', route('report-total-calls', [
            'company' => $company->id
        ]), [
            'start_date' => today()->setTimeZone($this->user->timezone)->subDays(16),
            'end_date'   => today()->setTimeZone($this->user->timezone),
            'date_type'  => 'CUSTOM',
            'vs_previous_period' => 1
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'kind'  => 'Report',
            'link'   => route('report-total-calls', [ 
                'company' => $company->id
            ]),
            'data'  => [
                'type'     => 'timeframe',
                'title'    => 'Calls',
                'datasets' => [
                    [
                        'total' => 16
                    ],
                    [
                        'total' => 0
                    ]
                ]
            ]
        ]);

        $response = $this->json('GET', route('report-total-calls', [
            'company' => $company->id
        ]), [
            'date_type' => 'ALL_TIME' ,
            'vs_previous_period' => 1
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'kind'  => 'Report',
            'link'   => route('report-total-calls', [ 
                'company' => $company->id
            ]),
            'data'  => [
                'type'     => 'timeframe',
                'title'    => 'Calls',
                'datasets' => [
                    [
                        'total' => 16
                    ],
                    [
                        'total' => 0
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
        $this->user->timezone = 'America/New_York';
        $this->user->save();
        $company        = $this->createCompany([
            'created_at' => now()->subDays(50)
        ]);
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
            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(2)
            ]);

            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(3)
            ]);

            //  Create two out of date range
            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(9)
            ]);

            factory(Call::class)->create([
                'account_id'      => $contact->account_id,
                'company_id'      => $contact->company_id,
                'contact_id'      => $contact->id,
                'phone_number_id' => $phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
                'created_at'      => now()->subDays(10)
            ]);
        });

        $startDate = now()->setTimeZone($this->user->timezone)->subDays(7);
        $endDate   = now()->setTimeZone($this->user->timezone);

        $response = $this->json('GET', route('report-call-sources', [
            'company'  => $company->id,
            'group_by' => 'calls.source'
        ]), [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date'   => $endDate->format('Y-m-d'),
            'date_type'  => 'CUSTOM'
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'kind'  => 'Report',
            'link'   => route('report-call-sources', [ 
                'company' => $company->id
            ]),
            'data'  => [
                'type'     => 'count',
                'datasets' => [
                    [ 'total' => 8 ]
                ],
            ]
        ]);

        //
        //  Try again with range that covers all calls
        //
        $response = $this->json('GET', route('report-call-sources', [
            'company' => $company->id,
            'group_by' => 'calls.source'
        ]), [
            'start_date' => today()->setTimeZone($this->user->timezone)->subDays(12),
            'end_date'   => today()->setTimeZone($this->user->timezone),
            'date_type'  => 'CUSTOM'
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'kind'  => 'Report',
            'link'   => route('report-call-sources', [ 
                'company' => $company->id
            ]),
            'data'  => [
                'type'     => 'count',
                'datasets' => [
                    [
                        'total' => 16
                    ]
                ],
                
            ]
        ]);
    }

    /**
     * Test creating a timeframe report
     * 
     * @group reports
     */
    public function testCreateTimeframeReport()
    {
        $company  = $this->createCompany();
        $reportService = App::make(ReportService::class);

        foreach( $reportService->conditionFields as $field ){
            $days     = mt_rand(0, 700);
            $response = $this->json('POST', route('create-report', [
                'company' => $company->id
            ]), [
                'name'        => 'Report 1',
                'module'      => 'calls',
                'date_type'   => 'LAST_N_DAYS',
                'last_n_days' => $days,
                'type'        => 'timeframe',
                'conditions'  => json_encode([
                    [
                        [
                            'field' => $field,
                            'operator' => 'EQUALS',
                            'inputs' => [
                                str_random(10)
                            ]
                        ]
                    ]
                ])
            ]);
            $response->assertStatus(201);
            $response->assertJSON([
                "account_id" => $this->account->id,
                "company_id" => $company->id,
                "created_by" => $this->user->id,
                "name"       => "Report 1",
                "module"     => "calls",
                "type"        => "timeframe",
                "date_type"   => "LAST_N_DAYS",
                "group_by"    => null,
                "last_n_days" => $days,
                "start_date" => null,
                "end_date"   => null,
                "conditions" => [],
                "kind"       => "Report"
            ]);
        }
    }

    /**
     * Test creating a count report
     * 
     * @group reports
     */
    public function testCreateCountReport()
    {
        $company  = $this->createCompany();
        $reportService = App::make(ReportService::class);

        foreach( $reportService->conditionFields as $field ){
            $days     = mt_rand(0, 700);
            $response = $this->json('POST', route('create-report', [
                'company' => $company->id
            ]), [
                'name'      => 'Report 1',
                'module'    => 'calls',
                'date_type' => 'LAST_N_DAYS',
                'last_n_days' => $days,
                'type'      => 'count',
                'group_by'  => $field
            ]);
            $response->assertStatus(201);
            $response->assertJSON([
                "account_id" => $this->account->id,
                "company_id" => $company->id,
                "created_by" => $this->user->id,
                "name"       => "Report 1",
                "module"     => "calls",
                "type"       => "count",
                "date_type"  => "LAST_N_DAYS",
                "group_by"   => $field,
                "last_n_days" => $days,
                "start_date" => null,
                "end_date"   => null,
                "conditions" => [],
                "kind"       => "Report"
            ]);
        }
    }

    /**
     * Test reading a count report
     * 
     * @group reports
     */
    public function testReadCountReport()
    {
        $this->user->timezone = 'America/New_York';
        $this->user->save();
        $company = $this->createCompany();
        $report = factory(Report::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'type'       => 'count',
            'group_by'   => 'calls.source',
            'date_type'  => 'LAST_N_DAYS',
            'last_n_days'=> 7
        ]);

        $response = $this->json('GET', route('read-report', [
            'company'   => $company->id,
            'report'    => $report->id,
            'with_data' => 1
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            "account_id" => $this->account->id,
            "company_id" => $company->id,
            "created_by" => $this->user->id,
            "name"       => $report->name,
            "module"     => $report->module,
            "type"       => $report->type,
            "date_type"  => $report->date_type,
            "group_by"   => $report->group_by,
            "last_n_days"=> $report->last_n_days,
            "start_date" => $report->start_date,
            "end_date"   => $report->end_date,
            "conditions" => $report->conditions,
            "kind"       => $report->kind,
            "link"       => route('read-report', [
                'company' => $company->id,
                'report' => $report->id
            ]),
            "data" => [
                'type'     => $report->type,
                'title'    => 'Source',
                'labels'   => [],
                'datasets' => [],
            ]
        ]);
    }  
    
    /**
     * Test reading a timeframe report
     * 
     * @group reports
     */
    public function testReadTimeFrameReport()
    {
        $this->user->timezone = 'America/New_York';
        $this->user->save();
        $company = $this->createCompany();
        $report = factory(Report::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'type'       => 'timeframe',
            'group_by'   => null,
            'date_type'  => 'LAST_N_DAYS',
            'last_n_days'=> 7
        ]);

        $response = $this->json('GET', route('read-report', [
            'company'   => $company->id,
            'report'    => $report->id,
            'with_data' => 1
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            "account_id" => $this->account->id,
            "company_id" => $company->id,
            "created_by" => $this->user->id,
            "name"       => $report->name,
            "module"     => $report->module,
            "type"       => $report->type,
            "date_type"  => $report->date_type,
            "group_by"   => $report->group_by,
            "last_n_days"=> $report->last_n_days,
            "start_date" => $report->start_date,
            "end_date"   => $report->end_date,
            "conditions" => $report->conditions,
            "kind"       => $report->kind,
            "link"       => route('read-report', [
                'company' => $company->id,
                'report' => $report->id
            ]),
            "data" => [
                'type'     => $report->type,
                'title'    => ucfirst($report->module),
                'labels'   => [],
                'datasets' => [],
            ]
        ]);
    }  

    /**
     * Test updating a report
     * 
     * @group reports
     */
    public function testUpdateReport()
    {
        $company = $this->createCompany();
        $report = factory(Report::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'type'       => 'count'
        ]);

        $updatedReport = factory(Report::class)->make([
            'type'       => 'timeframe',
            'date_type'  => 'CUSTOM',
            'group_by'   => null,
            'start_date' => today()->subDays(1)->format('Y-m-d'),
            'end_date' => today()->format('Y-m-d'),
            'last_n_days' => null
        ]);

        $response = $this->json('PUT', route('update-report', [
            'company' => $company->id,
            'report' => $report->id
        ]), [
            'name'       => $updatedReport->name,
            'type'       => $updatedReport->type,
            'date_type'  => $updatedReport->date_type,
            'group_by'   => $updatedReport->group_by,
            'start_date' => $updatedReport->start_date,
            'end_date'   => $updatedReport->end_date
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            "account_id" => $this->account->id,
            "company_id" => $company->id,
            "created_by" => $this->user->id,
            "name"       => $updatedReport->name,
            "module"     => $updatedReport->module,
            "type"       => $updatedReport->type,
            "date_type"  => $updatedReport->date_type,
            "group_by"   => $updatedReport->group_by,
            "last_n_days"=> $updatedReport->last_n_days,
            "start_date" => $updatedReport->start_date,
            "end_date"   => $updatedReport->end_date,
            "conditions" => $updatedReport->conditions,
            "kind"       => $updatedReport->kind,
            "link"       => route('read-report', [
                'company' => $company->id,
                'report'  => $report->id
            ])
        ]);
    }   

    /**
     * Test deleting a report
     * 
     * @group reports
     */
    public function testDeleteReport()
    {
        $company = $this->createCompany();
        $report = factory(Report::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);
        $schedule = factory(ScheduledExport::class)->create([
            'company_id' => $company->id,
            'report_id'  => $report->id
        ]);

        $response = $this->json('DELETE', route('delete-report', [
            'company' => $company->id,
            'report'  => $report->id
        ]));
        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'Deleted'
        ]);

        $this->assertDatabaseMissing('reports', [
            'id' => $report->id,
            'deleted_at' => null
        ]);
        $this->assertDatabaseMissing('scheduled_exports', [
            'id' => $schedule->id
        ]);

    }
}
