<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Twilio\Rest\Client as Twilio;
use \App\Models\Company\PhoneNumberConfig;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\Call;
use \Tests\Models\OnlineUser;

class OnlineTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test initializing for a campaign
     * 
     * @group online
     */
    public function testInitOnlineCampaign()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);

        //  Make valid rule 
        $phoneNumber = $this->createPhoneNumber($company, $config, [
            'swap_rules' => $this->makeSwapRules([ 
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'type' => 'ALL'
                            ]
                        ]
                    ]
                ]
            ]),
            'created_at' => now()->subSeconds(1)
        ]);

       //  Create Noise
       $this->createPhoneNumber($company, $config, [
            'swap_rules' => $this->makeSwapRules([
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'type' => 'DIRECT',
                            ]
                        ]
                    ]
                ]
            ]),
       ]);

       $this->createPhoneNumber($company, $config, [
            'swap_rules' => $this->makeSwapRules([
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'type' => 'PAID',
                            ]
                        ]
                    ]
                ]
            ]) 
       ]);

        $onlineUser = factory(OnlineUser::class)->make();

        $response = $this->json('POST', route('online-init'), [
            'company_id'     => $company->id,
            'http_referrer'  => $onlineUser->http_referrer,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ], [
            'User-Agent' => $onlineUser->user_agent
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'swap',
            'data'   => [
                'number' => [
                    'uuid' => $phoneNumber->uuid,
                ],
                'swap_rules' => json_decode(json_encode($phoneNumber->swap_rules), true)
            ]
        ]);

        $response->assertJSONStructure([
            'data' => [
                'persisted_id'
            ]
        ]);
    }

    /**
     * Test initializing for a campaign with a single browser type
     * 
     * @group online
     */
    public function testInitOnlineCampaignForSingleBrowserType()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);

        //  Make valid rule 
        $phoneNumber = $this->createPhoneNumber($company, $config, [
            'swap_rules' => $this->makeSwapRules([
                'browser_types' => ['CHROME']
            ]),
        ]);

        //  Try non-chrome
        $onlineUser = factory(OnlineUser::class)->make([
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_5 rv:3.0; sl-SI) AppleWebKit/534.18.6 (KHTML, like Gecko) Version/4.0.4 Safari/534.18.6'
        ]);
        
        $response = $this->json('POST', route('online-init'), [
            'company_id'     => $company->id,
            'http_referrer'  => $onlineUser->http_referrer,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ], [
            'User-Agent' => $onlineUser->user_agent
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'none',
            'data'   => null
        ]);

        //  Then chrome
        $onlineUser = factory(OnlineUser::class)->make([
            'user_agent' => 'Mozilla/5.0 (Macintosh; PPC Mac OS X 10_6_2) AppleWebKit/5352 (KHTML, like Gecko) Chrome/36.0.870.0 Mobile Safari/5352'
        ]);
        
        $response = $this->json('POST', route('online-init'), [
            'company_id'     => $company->id,
            'http_referrer'  => $onlineUser->http_referrer,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ], [
            'User-Agent' => $onlineUser->user_agent
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'swap',
            'data'   => [
                'number' => [
                    'uuid' => $phoneNumber->uuid,
                ],
                'swap_rules' => json_decode(json_encode($phoneNumber->swap_rules), true)
            ]
        ]);
    }

    /**
     * Test initializing for a campaign with a single device type
     * 
     * @group online
     */
    public function testInitOnlineCampaignForSingleDeviceType()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);

        //  Make valid rule 
        $phoneNumber = $this->createPhoneNumber($company, $config, [
            'swap_rules' => $this->makeSwapRules([
                'device_types' => ['TABLET']
            ]),
        ]);

        //  Try non-tablet
        $onlineUser = factory(OnlineUser::class)->make([
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_5 rv:3.0; sl-SI) AppleWebKit/534.18.6 (KHTML, like Gecko) Version/4.0.4 Safari/534.18.6'
        ]);
        
        $response = $this->json('POST', route('online-init'), [
            'company_id'     => $company->id,
            'http_referrer'  => $onlineUser->http_referrer,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ], [
            'User-Agent' => $onlineUser->user_agent
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'none',
            'data'   => null
        ]);

        //  Then tablet
        $onlineUser = factory(OnlineUser::class)->make([
            'user_agent' => 'Mozilla/5.0 (iPad; CPU OS 7_2_2 like Mac OS X; sl-SI) AppleWebKit/534.13.1 (KHTML, like Gecko) Version/4.0.5 Mobile/8B117 Safari/6534.13.1'
        ]);
        
        $response = $this->json('POST', route('online-init'), [
            'company_id'     => $company->id,
            'http_referrer'  => $onlineUser->http_referrer,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ], [
            'User-Agent' => $onlineUser->user_agent
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'swap',
            'data'   => [
                'number' => [
                    'uuid' => $phoneNumber->uuid,
                ],
                'swap_rules' => json_decode(json_encode($phoneNumber->swap_rules), true)
            ]
        ]);
    }

    /**
     * Test initializing for a campaign with multiple rules
     * 
     * @group online
     */
    public function testInitOnlineCampaignWithMultipleRules()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);

        //  Make valid rule 
        $phoneNumber = $this->createPhoneNumber($company, $config, [
            'swap_rules' => $this->makeSwapRules([
                'device_types'    => ['TABLET'],
                'browser_types'   => ['SAFARI'],
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'type' => 'DIRECT', 
                            ]
                        ]
                    ]
                ]
            ]),
            'created_at' => now()->subSeconds(1)
        ]);

        //  Add Noise
        $this->createPhoneNumber($company, $config, [
            'swap_rules' => $this->makeSwapRules([
                'device_types'    => ['TABLET'],
                'browser_types'   => ['CHROME'],
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'type' => 'DIRECT', 
                            ]
                        ]
                    ]
                ]
            ]),
        ]);

        $this->createPhoneNumber($company, $config, [
            'swap_rules' => $this->makeSwapRules([
                'device_types'    => ['DESKTOP'],
                'browser_types'   => ['EDGE'],
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'type' => 'DIRECT', 
                            ]
                        ]
                    ]
                ]
            ]),
        ]);

        //
        //  Check non-tablet fails
        //
        $onlineUser = factory(OnlineUser::class)->make([
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_5 rv:3.0; sl-SI) AppleWebKit/534.18.6 (KHTML, like Gecko) Version/4.0.4 Safari/534.18.6'
        ]);
        
        $response = $this->json('POST', route('online-init'), [
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ], [
            'User-Agent' => $onlineUser->user_agent
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'none',
            'data'   => null
        ]);

        //
        //  Check non-safari fails
        //
        $onlineUser = factory(OnlineUser::class)->make([
            // Crome
            'user_agent' => 'Mozilla/5.0 (Macintosh; PPC Mac OS X 10_6_2) AppleWebKit/5352 (KHTML, like Gecko) Chrome/36.0.870.0 Mobile Safari/5352'
        ]);
        
        $response = $this->json('POST', route('online-init'), [
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ], [
            'User-Agent' => $onlineUser->user_agent
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'none',
            'data'   => null
        ]);

        //
        //  Check non-direct fails
        //
        $onlineUser = factory(OnlineUser::class)->make([
            //  Tablet, Safari
            'user_agent' => 'Mozilla/5.0 (iPad; CPU OS 7_2_2 like Mac OS X; sl-SI) AppleWebKit/534.13.1 (KHTML, like Gecko) Version/4.0.5 Mobile/8B117 Safari/6534.13.1'
        ]);
        
        $response = $this->json('POST', route('online-init'), [
            'company_id'     => $company->id,
            'http_referrer'  => $onlineUser->http_referrer,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ], [
            'User-Agent' => $onlineUser->user_agent
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'none',
            'data'   => null
        ]);

        //  Check direct, tablet with safari passes
        $onlineUser = factory(OnlineUser::class)->make([
            'user_agent' => 'Mozilla/5.0 (iPad; CPU OS 7_2_2 like Mac OS X; sl-SI) AppleWebKit/534.13.1 (KHTML, like Gecko) Version/4.0.5 Mobile/8B117 Safari/6534.13.1'
        ]);
        
        $response = $this->json('POST', route('online-init'), [
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ], [
            'User-Agent' => $onlineUser->user_agent
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'swap',
            'data'   => [
                'number' => [
                    'uuid' => $phoneNumber->uuid,
                ],
                'swap_rules' => json_decode(json_encode($phoneNumber->swap_rules), true)
            ]
        ]);
    }

    /**
     * Test initializing for a campaign with different rule types do not collide
     * 
     * @group online
     */
    public function testInitOnlineCampaignDoesNotHaveCollisions()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);

        //  Make valid rule that matches all
        $allPhone = $this->createPhoneNumber($company, $config, [
            'swap_rules' => $this->makeSwapRules([
                'device_types'    => ['ALL'],
                'browser_types'   => ['ALL'],
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'type' => 'ALL', 
                            ]
                        ]
                    ]
                ]
            ]),
            'created_at' => now()->subSeconds(1)
        ]);

        //  Make a phone for direct
        $directPhone = $this->createPhoneNumber($company, $config, [
            'swap_rules' => $this->makeSwapRules([
                'device_types'    => ['ALL'],
                'browser_types'   => ['ALL'],
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'type' => 'DIRECT', 
                            ]
                        ]
                    ]
                ]
            ]),
        ]);

        //  Make a phone for organic(search)
        $organicPhone = $this->createPhoneNumber($company, $config, [
            'swap_rules' => $this->makeSwapRules([
                'device_types'    => ['ALL'],
                'browser_types'   => ['ALL'],
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'type' => 'ORGANIC', 
                            ]
                        ]
                    ]
                ]
            ])
        ]);

        //  Make a phone for paid
        $paidPhone = $this->createPhoneNumber($company, $config, [
            'swap_rules' => $this->makeSwapRules([
                'device_types'    => ['ALL'],
                'browser_types'   => ['ALL'],
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'type' => 'PAID', 
                            ]
                        ]
                    ]
                ]
            ]),
        ]);

        //  Make a phone for paid search(unpaid search is organic)
        $paidSearchPhone = $this->createPhoneNumber($company, $config, [
            'swap_rules' => $this->makeSwapRules([
                'device_types'    => ['ALL'],
                'browser_types'   => ['ALL'],
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'type' => 'PAID', 
                            ],
                            [
                                'type' => 'SEARCH', 
                            ]
                        ]
                    ],
                ]
            ]),
        ]);

        //  Make a phone for referral
        $referralPhone = $this->createPhoneNumber($company, $config, [
            'swap_rules' => $this->makeSwapRules([
                'device_types'    => ['ALL'],
                'browser_types'   => ['ALL'],
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'type' => 'REFERRAL', 
                            ]
                        ]
                    ]
                ]
            ]),
        ]);

        //  Make a phone for referrer
        $referrerPhone = $this->createPhoneNumber($company, $config, [
            'swap_rules' => $this->makeSwapRules([
                'device_types'    => ['ALL'],
                'browser_types'   => ['ALL'],
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'type' => 'REFERRER',
                                'operator' => 'IN',
                                'inputs' => [
                                    'https://postboxes.com'
                                ] 
                            ]
                        ]
                    ]
                ]
            ]),
        ]);

        //  Make a phone for landing path
        $landingPathPhone = $this->createPhoneNumber($company, $config, [
            'swap_rules' => $this->makeSwapRules([
                'device_types'    => ['ALL'],
                'browser_types'   => ['ALL'],
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'field'=> 'utm_medium',
                                'type' => 'LANDING_PATH',
                                'operator' => 'IN',
                                'inputs' => [
                                    '/landing-page-1/',
                                    '/landing-page-2/',
                                ] 
                            ]
                        ]
                    ]
                ]
            ]),
        ]);

        //  Make a phone for landing param
        $landingParamPhone = $this->createPhoneNumber($company, $config, [
            'swap_rules' => $this->makeSwapRules([
                'device_types'    => ['ALL'],
                'browser_types'   => ['ALL'],
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'field'=> 'utm_medium',
                                'type' => 'LANDING_PARAM',
                                'operator' => 'EQUALS',
                                'inputs' => [
                                    'email'
                                ] 
                            ]
                        ]
                    ]
                ]
            ]),
        ]);

        //
        //  Check direct passes
        //
        $onlineUser = factory(OnlineUser::class)->make();

        $response = $this->json('POST', route('online-init'), [
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'swap',
            'data'   => [
                'number' => [
                    'uuid' => $directPhone->uuid
                ]
            ]
        ]);

        //
        //  Check organic search passes
        //
        $onlineUser = factory(OnlineUser::class)->make();

        $response = $this->json('POST', route('online-init'), [
            'http_referrer'  => 'https://www.google.com/',
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'swap',
            'data'   => [
                'number' => [
                    'uuid' => $organicPhone->uuid
                ]
            ]
        ]);

        //
        //  Check paid passes
        //
        $onlineUser = factory(OnlineUser::class)->make();

        $response = $this->json('POST', route('online-init'), [
            'http_referrer'  => $onlineUser->http_referrer,
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->paid_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'swap',
            'data'   => [
                'number' => [
                    'uuid' => $paidPhone->uuid
                ]
            ]
        ]);

        //
        //  Check paid search passes
        //
        $onlineUser = factory(OnlineUser::class)->make();

        $response = $this->json('POST', route('online-init'), [
            'http_referrer'  => 'https://search.yahoo.com/',
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->paid_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'swap',
            'data'   => [
                'number' => [
                    'uuid' => $paidSearchPhone->uuid
                ]
            ]
        ]);

        //
        //  Check referral passes
        //
        $onlineUser = factory(OnlineUser::class)->make();

        $response = $this->json('POST', route('online-init'), [
            'http_referrer'  => $onlineUser->http_referrer,
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'swap',
            'data'   => [
                'number' => [
                    'uuid' => $referralPhone->uuid
                ]
            ]
        ]);


        //
        //  Check refferer passes
        //
        $onlineUser = factory(OnlineUser::class)->make();

        $response = $this->json('POST', route('online-init'), [
            'http_referrer'  => 'http://postboxes.com/forward',
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'swap',
            'data'   => [
                'number' => [
                    'uuid' => $referrerPhone->uuid
                ]
            ]
        ]);

        //
        //  Check landing path
        //
        $onlineUser = factory(OnlineUser::class)->make();

        $response = $this->json('POST', route('online-init'), [
            'http_referrer'  => $onlineUser->http_referrer,
            'company_id'     => $company->id,
            'entry_url'      => 'https://my.site/landing-page-1',
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'swap',
            'data'   => [
                'number' => [
                    'uuid' => $landingPathPhone->uuid
                ]
            ]
        ]);

        //
        //  Check landing param
        //
        $onlineUser = factory(OnlineUser::class)->make();

        $response = $this->json('POST', route('online-init'), [
            'http_referrer'  => $onlineUser->http_referrer,
            'company_id'     => $company->id,
            'entry_url'      => 'https://my.site/home?utm_medium=email',
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'swap',
            'data'   => [
                'number' => [
                    'uuid' => $landingParamPhone->uuid
                ]
            ]
        ]);
    }
    
}
