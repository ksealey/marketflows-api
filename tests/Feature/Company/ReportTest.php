<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use \App\Models\Company\Report;
use \App\Models\Company\ReportAutomation;
use \App\Models\Company\Call;
use \App\Jobs\ExportResultsJob;
use \DateTimeZone;
use Queue;

class ReportTest extends TestCase
{
    use \Tests\CreatesAccount;

   /**
     * Test listing reports
     * 
     * @group reports
     */
    public function testListingReports()
    {
        $company     = $this->createCompany();
        $report      = factory(Report::class, 10)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('GET', route('list-reports', [
            'company' => $company->id,
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
                    'start_date',
                    'end_date'
                ]
            ]
        ]);
    }

    /**
     * Test listing reports with conditions
     * 
     * @group reports
     */
    public function testListingReportsWithConditions()
    {
        $company     = $this->createCompany();
        $reports     = factory(Report::class, 10)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);
        $firstReport = $reports->first();

        $response = $this->json('GET', route('list-reports', [
            'company' => $company->id,
        ]), [
            'conditions' => json_encode([
                [
                    'field' =>  'reports.name',
                    'operator' => 'EQUALS',
                    'inputs' => [ $firstReport->name ]
                ]
            ])
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            "result_count" => 1,
            "limit"        => 250,
            "page"         => 1,
            "total_pages"  => 1,
            "next_page"    => null,
        ]);

        $response->assertJSON([
            "results" => [
                [
                    'account_id' => $firstReport->account_id,
                    'company_id' => $firstReport->company_id,
                    'name'       => $firstReport->name,
                    'start_date' => $firstReport->start_date,
                    'end_date'   => $firstReport->end_date
                ]
            ]
        ]);
    }

   /**
     * Test listing reports with date ranges
     * 
     * @group reports
     */
    public function testListReportsWithDateRanges()
    {
        $company     = $this->createCompany();
        $twoDaysAgo  = now()->subDays(2);

        $oldReport  = factory(Report::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'created_at' => $twoDaysAgo,
            'updated_at' => $twoDaysAgo
        ]);

        $reports = factory(Report::class, 10)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'created_by' => $this->user->id
        ]);
        
        $twoDaysAgo->setTimeZone(new DateTimeZone($this->user->timezone));
        $response = $this->json('GET', route('list-reports', [
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
            "total_pages"  => 1,
            "next_page"    => null,
            "results" => [
                [
                    'id'   => $oldReport->id,    
                    'name' => $oldReport->name,
                   
                ]
            ]
        ]);
    }


    /**
     * Test creating a report
     * 
     * @group reports
     */
    public function testCreateReport()
    {
        $company = $this->createCompany();
        $report = factory(Report::class)->make();

        $startDate = now()->subDays(5)->format('Y-m-d');
        $endDate   = now()->subDays(4)->format('Y-m-d');
        $conditions= [
            [
                'field' => 'calls.source',
                'operator' => 'EQUALS',
                'inputs' => [ 'Facebook' ]
            ]
        ];
        $response = $this->json('POST', route('create-report', [
            'company' => $company->id
        ]), [
            'name' => $report->name,
            'module' => $report->module,
            'timezone' => $report->timezone,
            'date_type' => $report->date_type,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'comparisons' => json_encode([1,2]),
            'conditions' => json_encode($conditions)
        ]);
        $response->assertStatus(201);
        $response->assertJSON([
            'account_id'    => $this->account->id,
            'company_id'    => $company->id,
            'name'          => $report->name,
            'module'        => $report->module,
            'timezone'      => $report->timezone,
            'date_type'     => $report->date_type,
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'comparisons'   => [1,2],
            'conditions'    => $conditions
        ]);

        $this->assertDatabaseHas('reports', [
            'name'       => $report->name,
            'account_id' => $this->account->id,
            'company_id' => $company->id
        ]);
    }

    /**
     * Test reading a report
     * 
     * @group reports
     */
    public function testReadeport()
    {
        $company = $this->createCompany();

        $conditions= [
            [
                'field' => 'calls.source',
                'operator' => 'EQUALS',
                'inputs' => [ 'Facebook' ]
            ]
        ];

        $comparisons = [1, 2, 3];

        $report = factory(Report::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'conditions' => json_encode($conditions),
            'comparisons' => json_encode($comparisons)
        ]);

        $automations = factory(ReportAutomation::class, 10)->create([
            'report_id' => $report->id
        ]);

        $response = $this->json('GET', route('read-report', [
            'company' => $company->id,
            'report'  => $report->id
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            'name' => $report->name,
            'timezone' => $report->timezone,
            'conditions' => $conditions,
            'comparisons' => $comparisons
            
        ]);
    }

    /**
     * Test exporting reports
     * 
     * @group reports--
     */
    public function testExportReports()
    {
        Queue::fake();
        
        $company = $this->createCompany();

        $reports = factory(Report::class, 10)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('GET', route('export-reports', [
            'company' => $company->id
        ]));

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'queued'
        ]);

        Queue::assertPushed(ExportResultsJob::class, function ($job){
            return $job->model === Report::class 
                && $job->user->id === $this->user->id;
        });
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
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $reportData = factory(Report::class)->make([
            'date_type'  => 'CUSTOM',
            'start_date' => now()->subDays(5)->format('Y-m-d'),
            'end_date'   => now()->subDays(1)->format('Y-m-d'),
            'comparisons'  => json_encode([1,2,3]),
            'conditions' => json_encode([
                [
                    'field' => 'calls.source',
                    'operator' => 'EQUALS',
                    'inputs' => [ 'Facebook' ]
                ]
            ])
        ]);

        $response = $this->json('PUT', route('update-report', [
            'company' => $company->id,
            'report'  => $report->id
        ]), [
            'name' => $reportData->name,
            'module' => $reportData->module,
            'timezone' => $reportData->timezone,
            'date_type' => $reportData->date_type,
            'start_date' => $reportData->start_date,
            'end_date' => $reportData->end_date,
            'comparisons'  => json_encode($reportData->comparisons),
            'conditions'   => json_encode($reportData->conditions)
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'account_id'    => $this->account->id,
            'company_id'    => $company->id,
            'name'          => $reportData->name,
            'module'        => $reportData->module,
            'timezone'      => $reportData->timezone,
            'date_type'     => $reportData->date_type,
            'start_date'    => $reportData->start_date,
            'end_date'      => $reportData->end_date,
            'comparisons'   => json_decode(json_encode($reportData->comparisons), true),
            'conditions'    => json_decode(json_encode($reportData->conditions), true)
        ]);

        $this->assertDatabaseHas('reports', [
            'name'       => $reportData->name,
            'account_id' => $this->account->id,
            'company_id' => $company->id
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
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $automations = factory(ReportAutomation::class, 10)->create([
            'report_id' => $report->id
        ]);

        $response = $this->json('DELETE', route('delete-report', [
            'company' => $company->id,
            'report'  => $report->id
        ]));

        $response->assertStatus(200);

        $response->assertJSON([
            'message'    => 'Deleted'
        ]);

        $this->assertDatabaseHas('reports', [
            'id'         => $report->id,
            'deleted_by' => $this->user->id,
        ]);
        $this->assertDatabaseMissing('reports', [
            'id'         => $report->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseMissing('report_automations', [
            'report_id' => $report->id
        ]);
    }

    /**
     * Test viewing a non-comparison report chart
     * 
     * @group reports
     */
    public function testViewNonComparisonChart()
    {
        $company = $this->createCompany();
        $config  = $this->createConfig($company);
        $phoneNumber = $this->createPhoneNumber($company, $config);

        $report = factory(Report::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'comparisons' => null,
            'conditions'  => null,
            'module'      => 'calls',
            'metric'      => 'calls.source',
            'date_type'   => 'LAST_7_DAYS'
        ]);

        $source = str_random(40);
        $count  = mt_rand(1, 10);
        $calls = factory(Call::class, $count)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_id' => $phoneNumber->id,
            'source'     => $source,
            'created_at' => now()->subDays(2)
        ]);

        $source2 = str_random(40);
        $count2  = mt_rand(1, 10);
        $calls2 = factory(Call::class, $count2)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_id' => $phoneNumber->id,
            'source'     => $source2,
            'created_at' => now()->subDays(2)
        ]);

        $response = $this->json('GET', route('read-report-chart', [
            'company' => $company->id,
            'report'  => $report->id
        ]));
        $sevenDaysAgo = now()->subDays(7);
        $sevenDaysAgo->setTimeZone(new DateTimeZone($report->timezone));
        $sevenDaysAgo = $sevenDaysAgo->format('M, j Y');

        $yesterday = now()->subDays(1);
        $yesterday->setTimeZone(new DateTimeZone($report->timezone));
        $yesterday = $yesterday->format('M, j Y');

        $response->assertStatus(200);
        $response->assertJSONStructure([
            'charts' => [
                [
                    'type',
                    'title',
                    'labels' => [],
                    'step_size',
                    'datasets' => [
                        [
                            'label',
                            'data'  => [
                                [
                                    "label",
                                    "x",
                                    "y"
                                ]
                            ]
                        ],
                        [
                            'label',
                            'data'  => [
                                [
                                    "label",
                                    "x",
                                    "y"
                                ]
                            ]
                        ],
                    ],
                    'is_comparison'
                ]
            ]
        ]);
    }

    /**
     * Test viewing a non-comparison report chart
     * 
     * @group reports
     */
    public function testViewComparisonChart()
    {
        $company = $this->createCompany();
        $config  = $this->createConfig($company);
        $phoneNumber = $this->createPhoneNumber($company, $config);

        $report = factory(Report::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'comparisons' => json_encode([1,2,3]),
            'conditions'  => null,
            'module'      => 'calls',
            'metric'      => 'calls.source',
            'date_type'   => 'LAST_7_DAYS'
        ]);

        $source = str_random(40);
        $count  = mt_rand(1, 10);
        $calls = factory(Call::class, $count)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_id' => $phoneNumber->id,
            'source'     => $source,
            'created_at' => now()->subDays(2)
        ]);

        $source2 = str_random(40);
        $count2  = mt_rand(1, 10);
        $calls2 = factory(Call::class, $count2)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_id' => $phoneNumber->id,
            'source'     => $source2,
            'created_at' => now()->subDays(2)
        ]);

        $response = $this->json('GET', route('read-report-chart', [
            'company' => $company->id,
            'report'  => $report->id
        ]));
        $response->assertStatus(200);

        $sevenDaysAgo = now()->subDays(7);
        $sevenDaysAgo->setTimeZone(new DateTimeZone($report->timezone));
        $sevenDaysAgo = $sevenDaysAgo->format('M, j Y');

        $yesterday = now()->subDays(1);
        $yesterday->setTimeZone(new DateTimeZone($report->timezone));
        $yesterday = $yesterday->format('M, j Y');
        $response->assertJSONStructure([
            'charts' => [
                [
                    'type',
                    'title',
                    'labels' => [],
                    'step_size',
                    'datasets' => [
                        [
                            'label',
                            'data'  => [ [ "label", "x", "y" ], [ "label", "x", "y" ], [ "label", "x", "y" ], [ "label", "x", "y" ] ]
                        ],
                        [
                            'label',
                            'data'  => [ [ "label", "x", "y" ], [ "label", "x", "y" ], [ "label", "x", "y" ], [ "label", "x", "y" ] ]
                        ],
                        
                    ],
                    'is_comparison'
                ]
            ]
        ]);
    }

     /**
     * Test viewing a conditioned report chart
     * 
     * @group reports--
     */
    public function testViewConditionedChart()
    {
        $company = $this->createCompany();
        $config  = $this->createConfig($company);
        $phoneNumber = $this->createPhoneNumber($company, $config);

        $source = str_random(40);

        $report = factory(Report::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'comparisons' => json_encode([1,2,3]),
            'conditions'  => json_encode([
                [
                    'field' => 'calls.source',
                    'operator' => 'EQUALS',
                    'inputs' => [ $source ]
                ]
            ]),
            'module'      => 'calls',
            'metric'      => 'calls.source',
            'date_type'   => 'LAST_7_DAYS'
        ]);

        $count  = mt_rand(1, 10);
        $calls = factory(Call::class, $count)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_id' => $phoneNumber->id,
            'source'     => $source,
            'created_at' => now()->subDays(2)
        ]);

        $source2 = str_random(40);
        $count2  = mt_rand(1, 10);
        $calls2 = factory(Call::class, $count2)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_id' => $phoneNumber->id,
            'source'     => $source2,
            'created_at' => now()->subDays(2)
        ]);

        $response = $this->json('GET', route('read-report-chart', [
            'company' => $company->id,
            'report'  => $report->id
        ]));
        $response->assertStatus(200);

        $sevenDaysAgo = now()->subDays(7);
        $sevenDaysAgo->setTimeZone(new DateTimeZone($report->timezone));
        $sevenDaysAgo = $sevenDaysAgo->format('M, j Y');

        $yesterday = now()->subDays(1);
        $yesterday->setTimeZone(new DateTimeZone($report->timezone));
        $yesterday = $yesterday->format('M, j Y');
        $response->assertJSONStructure([
            'charts' => [
                [
                    'type',
                    'title',
                    'labels' => [],
                    'step_size',
                    'datasets' => [
                        [
                            'label',
                            'data'  => [ [ "label", "x", "y" ] ]
                        ],
                    ],
                    'is_comparison'
                ]
            ]
        ]);
    }
}
