<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use \App\Models\Company;
use \App\Models\Company\PhoneNumberConfig;
use \App\Models\Company\PhoneNumber;
use DateTime;
use DateTimeZone;

class WidgetTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test getting top call sources
     * 
     * @group widgets
     */
    public function testGetTopCallSources()
    {
        $data = $this->createCompanies();

        $response = $this->json('GET', route('widget-top-call-sources'));

        $response->assertJSON([
            "title" => "Top Call Sources",
            "type"  => "doughnut",
            "data"  => [
                "labels" =>  [
                    
                ],
                "datasets" => [
                    [
                        "data" => [
                            
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * Test getting total companies
     * 
     * @group widgets
     */
    public function testGetTotalCompanies()
    {
        $companyCount = mt_rand(5, 10);
        $companies    = factory(Company::class, $companyCount)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('GET', route('widget-total-companies'));

        $response->assertJSON([
            "title" => "Total Companies",
            "type"  => "count",
            "data"  => [
                "count" => $companyCount
            ]
        ]);
    }

    /**
     * Test getting total numbers
     * 
     * @group widgets
     */
    public function testGetTotalNumbers()
    {
        $numberCount = mt_rand(1,5);

        $company = $this->createCompany();
        $config  = factory(PhoneNumberConfig::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        factory(PhoneNumber::class, $numberCount)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('GET', route('widget-total-numbers'));

        $response->assertJSON([
            "title" => "Total Numbers",
            "type"  => "count",
            "data"  => [
                "count" => $numberCount
            ]
        ]);
    }


     /**
     * Test getting next bill
     * 
     * @group widgets
     */
    public function testGetBillingNextBill()
    {
        $this->createCompanies();

        $billAt  = new DateTime($this->account->billing->period_ends_at);
        $billAt->setTimeZone(new DateTimeZone($this->user->timezone));
        $usage   = $this->account->currentUsage();
        $storage = $this->account->currentStorage();

        $response = $this->json('GET', route('widget-billing-next-bill'));

        $response->assertJSON([
            'title' => 'Next Bill',
            'type'  => 'breakdown',
            'data'  => [
                'total' => number_format($usage['total']['cost'] + $storage['total']['cost'] + $this->account->monthly_fee, 2),
                'items' => [
                    [
                        'title' => 'Next Bill Date: ' . $billAt->format('M j, Y'),
                        'description' => '',
                        'items' => []
                    ],
                    [
                        'title' => 'Monthly Service Fee',
                        'description' => 'The monthly service fee charged for your account type.',
                        'items' => [
                            [
                                'label'       => $this->account->pretty_account_type,
                                'details'     => '',
                                'value'       => number_format($this->account->monthly_fee, 2)
                            ]
                        ]
                    ],
                    [
                        'title' => 'Usage',
                        'description' => 'The balance owed for all usage. This includes additional numbers and minutes along with call recordings and caller id lookups.',
                        'items' => [
                            [
                                'label'       => 'Total Usage',
                                'details'     => '',
                                'value'       => number_format($usage['total']['cost'], 2)
                            ]
                        ]
                    ],
                    [
                        'title' => 'Storage',
                        'description' => 'Storage costs for current period.',
                        'items' => [
                            [
                                'label'       => 'Total Storage',
                                'details'     => number_format($storage['total']['size_gb'],2) . 'GB',
                                'value'       => number_format($storage['total']['cost'],2)
                            ]
                        ]
                    ],
                ]
            ]
        
        ]);
    }



    /**
     * Test getting detailed usage info
     * 
     * @group widgets
     */
    public function testGetBillingCurrentUsageBalanceByItem()
    {
        $this->createCompanies();

        $response = $this->json('GET', route('widget-billing-current-usage-balance-by-item'));

        $response->assertJSON([
            'title' => 'Month-to-Date Balance by Item',
            'type'  => 'doughnut',
            'data'  => [
                'labels'   => ['Local Numbers', 'Local Minutes', 'Toll-Free Numbers', 'Toll-Free Minutes', 'Storage'],
                'datasets' => [
                    [ 
                        'data' => [
                            
                        ]
                    ]
                ],
                'total' => number_format($this->account->usageBalance(), 2)
            ]
        ]);
    }

    /**
     * Test billing current usage balance breakdown
     * 
     * @group widgets
     */
    public function testBillingCurrentUsageBalanceBreakdown()
    {
        $this->createCompanies();
        
        $userTZ             = new DateTimeZone($this->user->timezone);
        $billingPeriod      = $this->account->billing->current_billing_period;
        $startBillingPeriod = clone $billingPeriod['start'];
        $endBillingPeriod   = clone $billingPeriod['end'];

        $startBillingPeriod->setTimeZone($userTZ);
        $endBillingPeriod->setTimeZone($userTZ);

        $usage   = $this->account->currentUsage();
        $storage = $this->account->currentStorage();

        $response = $this->json('GET', route('widget-billing-current-usage-balance-breakdown'));

        $response->assertJSON([
            'title' => 'Month-to-Date Usage Breakdown',
            'type'  => 'breakdown',
            'data'  =>  [
                'total'  => number_format($usage['total']['cost'] + $storage['total']['cost'], 2),
                'items'  => [
                    [
                        'title'       => 'Billing Period: ' . $startBillingPeriod->format('M j, Y') . ' - ' . $endBillingPeriod->format('M j, Y'),
                        'description' => '',
                        'items' => []
                    ],
                    [
                        'title' => 'Local Numbers',
                        'description' => 'Owned local numbers and minutes used, including numbers deleted within the current billing period.',
                        'items' => [
                            [
                                'label'       => 'Numbers',
                                'details'     => $usage['local']['numbers']['count'],
                                'value'       => number_format($usage['local']['numbers']['cost'], 2)
                            ],
                            [
                                'label'       => 'Minutes',
                                'details'     => $usage['local']['minutes']['count'],
                                'value'       => number_format($usage['local']['minutes']['cost'], 2)
                            ],
                        ]
                    ],
                    [
                        'title' => 'Toll-Free Numbers',
                        'description' => 'Owned local numbers and minutes used, including numbers deleted within the current billing period.',
                        'items' => [
                            [
                                'label'       => 'Numbers',
                                'details'     => number_format($usage['toll_free']['numbers']['count']),
                                'value'       => number_format($usage['toll_free']['numbers']['cost'],2)
                            ],
                            [
                                'label'       => 'Minutes',
                                'details'     => number_format($usage['toll_free']['minutes']['count']),
                                'value'       => number_format($usage['toll_free']['minutes']['cost'],2)
                            ],
                        ]
                    ],
                    [
                        'title'       => 'Storage',
                        'description' => 'Storage costs for files and call recordings.',
                        'items' => [
                            [
                                'label'       => 'Call Recordings',
                                'details'     => number_format($storage['call_recordings']['size_gb'],2) . 'GB',
                                'value'       => number_format($storage['call_recordings']['cost'],2)
                            ],
                            [
                                'label'       => 'Files',
                                'details'     => number_format($storage['files']['size_gb'],2) . 'GB',
                                'value'       => number_format($storage['files']['cost'], 2)
                            ]
                        ]
                    ],
                    
                ]
            ]
        ]);
    }
}
