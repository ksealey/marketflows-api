<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Twilio\Rest\Client as Twilio;
use \App\Models\Company\PhoneNumberConfig;
use \App\Models\Company\PhoneNumberPool;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\Call;
use \App\Models\TrackingEntity;
use \App\Models\TrackingSession;
use \App\Models\TrackingSessionEvent;
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
                            ],
                            [
                                'field' => 'utm_source',
                                'type'  => 'LANDING_PARAM',
                                'operator' => 'EQUALS',
                                'inputs' => [
                                    'Google'
                                ]
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
            'entry_url'      => $onlineUser->paid_url . '&utm_source=google',
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

    /**
     * Test returning the correct response when no pool exists
     * 
     * @group online
     */
    public function testNoPoolExisting()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);

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
            'action' => 'none',
            'data'   => null
        ]);
    }

    /**
     * Test returning the correct response when pool is disabled
     * 
     * @group online
     */
    public function testPoolDisabled()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config, [
            'disabled_at' => now()
        ]);


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
            'action' => 'swap', // Will match a number and not the session
        ]);

        //  Remove numbers and make sure it returns nothing
        PhoneNumber::where('phone_number_pool_id', $pool->id)->delete();
        $response = $this->json('POST', route('online-init'), [
            'http_referrer'  => $onlineUser->http_referrer,
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'none',
            'data'   => null
        ]);
    }

    /**
     * Test returning the correct response when pool is enabled with invalid swap rules
     * 
     * @group online
     */
    public function testPoolWithInvalidSwapRules()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config, [
            'swap_rules' => $this->makeSwapRules([
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'type' => 'LANDING_PATH',
                                'operator' => 'EQUALS',
                                'inputs' => [
                                    '/' . str_random(30)
                                ]
                            ]
                        ]
                    ]
                ]
            ])
        ]);

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
            'action' => 'swap', // Will match a number and not the session
        ]);

        //  Remove numbers and make sure it returns nothing
        PhoneNumber::where('phone_number_pool_id', $pool->id)->delete();
        $response = $this->json('POST', route('online-init'), [
            'http_referrer'  => $onlineUser->http_referrer,
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'none',
            'data'   => null
        ]);
    }

     /**
     * Test that a number is assigned for a valid pool
     * 
     * @group online
     */
    public function testPoolAssignsNumber()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config);

        $onlineUser = factory(OnlineUser::class)->make();

        $source   = str_random(64);
        $medium   = str_random(64);
        $content  = str_random(64);
        $campaign = str_random(64);
        $keyword  = str_random(64);

        $entryURL = $onlineUser->entry_url . '?utm_source=' 
                                        . urlencode($source) 
                                        . '&utm_medium=' 
                                        . urlencode($medium) 
                                        . '&utm_content=' 
                                        . urlencode($content) 
                                        . '&utm_campaign=' 
                                        . urlencode($campaign)
                                        . '&keyword='
                                        . urlencode($keyword);

        $response = $this->json('POST', route('online-init'), [
            'http_referrer'  => $onlineUser->http_referrer,
            'company_id'     => $company->id,
            'entry_url'      => $entryURL,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ]);
        $response->assertStatus(200);
        $response->assertJSONStructure([
            'action',
            'data'=> [
                'number' => [
                    'uuid'
                ],
                'session' => [
                    'uuid'
                ],
                'tracking_entity_uuid'
            ]
        ]);

        $number = $pool->phone_numbers->first();
        $response->assertJSON([
            'action' => 'session',
            'data'=> [
                'number' => [
                    'uuid' => $number->uuid
                ],
            ]
        ]);

        $this->assertDatabaseHas('tracking_sessions', [
            'uuid'               => $response['data']['session']['uuid'],
            'tracking_entity_id' => TrackingEntity::where('uuid', $response['data']['tracking_entity_uuid'])->first()->id,
            'company_id'        => $company->id,
            'phone_number_id'   => $number->id,
            'source'            => $source,
            'medium'            => $medium,
            'content'           => $content,
            'campaign'          => $campaign,
            'keyword'           => $keyword
        ]);

        $this->assertDatabaseHas('tracking_session_events', [
            'tracking_session_id' => TrackingSession::where('uuid', $response['data']['session']['uuid'])->first()->id,
            'event_type'          => TrackingSessionEvent::SESSION_START,
        ]);

        $this->assertDatabaseHas('tracking_session_events', [
            'tracking_session_id' => TrackingSession::where('uuid', $response['data']['session']['uuid'])->first()->id,
            'event_type'          => TrackingSessionEvent::PAGE_VIEW,
            'content'             => $entryURL
        ]);
    }

    /**
     * Test that numbers are rotated properly
     * 
     * @group online
     */
    public function testPoolRotatesNumbers()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config);
        $numIdx      = 0;

        for($i = 0; $i < 10; $i++ ){
            if( ! isset($pool->phone_numbers[$numIdx]) )
                $numIdx = 0;

            $number = $pool->phone_numbers[$numIdx];
            
            $onlineUser = factory(OnlineUser::class)->make();

            $source   = str_random(64);
            $medium   = str_random(64);
            $content  = str_random(64);
            $campaign = str_random(64);
            $keyword  = str_random(64);
    
            $entryURL = $onlineUser->entry_url . '?utm_source=' 
                                            . urlencode($source) 
                                            . '&utm_medium=' 
                                            . urlencode($medium) 
                                            . '&utm_content=' 
                                            . urlencode($content) 
                                            . '&utm_campaign=' 
                                            . urlencode($campaign)
                                            . '&keyword='
                                            . urlencode($keyword);

            $response = $this->json('POST', route('online-init'), [
                'http_referrer'  => $onlineUser->http_referrer,
                'company_id'     => $company->id,
                'entry_url'      => $entryURL,
                'device_width'   => $onlineUser->device_width,
                'device_height'  => $onlineUser->device_height,
            ], [
                'User-Agent'      => $onlineUser->user_agent, // Add new agent and ip so it gets a new fingerprint
                'X-Forwarded-For' => $onlineUser->ip
            ]);

            $response->assertStatus(200);
            
            $response->assertJSON([
                'action' => 'session',
                'data'=> [
                    'number' => [
                        'uuid' => $number->uuid
                    ],
                ]
            ]);

            $this->assertDatabaseHas('tracking_sessions', [
                'uuid'              => $response['data']['session']['uuid'],
                'tracking_entity_id'=>  TrackingEntity::where('uuid', $response['data']['tracking_entity_uuid'])->first()->id,
                'company_id'        => $company->id,
                'phone_number_id'   => $number->id,
                'source'            => $source,
                'medium'            => $medium,
                'content'           => $content,
                'campaign'          => $campaign,
                'keyword'           => $keyword
            ]);

            $this->assertDatabaseHas('tracking_session_events', [
                'tracking_session_id' => TrackingSession::where('uuid', $response['data']['session']['uuid'])->first()->id,
                'event_type'          => TrackingSessionEvent::SESSION_START,
            ]);

            $this->assertDatabaseHas('tracking_session_events', [
                'tracking_session_id' => TrackingSession::where('uuid', $response['data']['session']['uuid'])->first()->id,
                'event_type'          => TrackingSessionEvent::PAGE_VIEW,
                'content'             => $entryURL
            ]);

            $numIdx++;
        }
    }

    /**
     * Test that numbers can be reused when a tracking entity id is provided
     * 
     * @group online
     */
    public function testPoolNumberIsReusedWhenTrackingIdProvided()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config);
        $number      = $pool->phone_numbers->first();

        //  Make initial request
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
            'action' => 'session',
            'data'=> [
                'number' => [
                    'uuid' => $number->uuid
                ],
                'session' => []
            ],
            
        ]);

        $trackingEntityUUID = $response['data']['tracking_entity_uuid'];

        //  Make second request using tracking_entity_uuid
        $onlineUser = factory(OnlineUser::class)->make();
        $response = $this->json('POST', route('online-init'), [
            'http_referrer'  => $onlineUser->http_referrer,
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
            'tracking_entity_uuid'   => $trackingEntityUUID,
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'session',
            'data'=> [
                'number' => [
                    'uuid' => $number->uuid
                ],
                'session' => [
                    
                ],
                'tracking_entity_uuid' => $trackingEntityUUID
            ]
        ]);

        //  Then make a third
        $onlineUser = factory(OnlineUser::class)->make();
        $response = $this->json('POST', route('online-init'), [
            'http_referrer'  => $onlineUser->http_referrer,
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
            'tracking_entity_uuid'   => $trackingEntityUUID,
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'session',
            'data'=> [
                'number' => [
                    'uuid' => $number->uuid
                ],
                'session' => [
                    
                ],
                'tracking_entity_uuid' => $trackingEntityUUID
            ]
        ]);

        $entities  = TrackingEntity::where('uuid', $trackingEntityUUID)->get();
        $this->assertTrue( count($entities) === 1 );

        $entity = $entities->first();
        $this->assertTrue(TrackingSession::where('tracking_entity_id', $entity->id)->count() === 3);
    }

    /**
     * Test that numbers can be reused when a fingerprint is provided
     * 
     * @group online
     */
    public function testPoolNumberIsReusedWhenFingerprintProvided()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config);
        $number      = $pool->phone_numbers->first();

        //  Make initial request
        $onlineUser = factory(OnlineUser::class)->make();
        
        $response = $this->json('POST', route('online-init'), [
            'http_referrer'  => $onlineUser->http_referrer,
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
            'fingerprint'    => $onlineUser->fingerprint
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'session',
            'data'=> [
                'number' => [
                    'uuid' => $number->uuid
                ],
                'session' => []
            ],
            
        ]);

        $trackingEntityUUID = $response['data']['tracking_entity_uuid'];

        //  Make request using the fingerprint from the first request, leaving the tracking identity out
        $onlineUser = factory(OnlineUser::class)->make([
            'fingerprint' => $onlineUser->fingerprint
        ]);
        $response   = $this->json('POST', route('online-init'), [
            'http_referrer'          => $onlineUser->http_referrer,
            'company_id'             => $company->id,
            'entry_url'              => $onlineUser->entry_url,
            'device_width'           => $onlineUser->device_width,
            'device_height'          => $onlineUser->device_height,
            'fingerprint'            => $onlineUser->fingerprint
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'session',
            'data'=> [
                'number' => [
                    'uuid' => $number->uuid
                ],
                'session' => [
                    
                ],
                'tracking_entity_uuid' => $trackingEntityUUID
            ]
        ]);

        //  Then make a third
        $onlineUser = factory(OnlineUser::class)->make([
            'fingerprint' => $onlineUser->fingerprint
        ]);
        $response = $this->json('POST', route('online-init'), [
            'http_referrer'  => $onlineUser->http_referrer,
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
            'fingerprint'    => $onlineUser->fingerprint
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'session',
            'data'=> [
                'number' => [
                    'uuid' => $number->uuid
                ],
                'session' => [
                    
                ],
                'tracking_entity_uuid' => $trackingEntityUUID
            ]
        ]);

        $entities  = TrackingEntity::where('uuid', $trackingEntityUUID)->get();
        $this->assertTrue( count($entities) === 1 );

        $entity = $entities->first();
        $this->assertTrue(TrackingSession::where('tracking_entity_id', $entity->id)->count() === 3);
    }

    /**
     * Test that numbers can be reused when a fingerprint could not be calculated and user agent is used
     * 
     * @group online
     */
    public function testPoolNumberIsReusedWhenFingerprintNotCalculated()
    {
        
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config);
        $number      = $pool->phone_numbers->first();

        //  Make initial request
        $onlineUser = factory(OnlineUser::class)->make();
        $response = $this->json('POST', route('online-init'), [
            'http_referrer'  => $onlineUser->http_referrer,
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ], [
            'User-Agent' => $onlineUser->user_agent
        ]);
        
        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'session',
            'data'=> [
                'number' => [
                    'uuid' => $number->uuid
                ],
                'session' => []
            ],
            
        ]);

        $trackingEntityUUID = $response['data']['tracking_entity_uuid'];

        //  Make second request using the user agent an only identifying info
        $onlineUser = factory(OnlineUser::class)->make([
            'user_agent' => $onlineUser->user_agent
        ]);
        $response = $this->json('POST', route('online-init'), [
            'http_referrer'  => $onlineUser->http_referrer,
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ], [
            'User-Agent' => $onlineUser->user_agent
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'session',
            'data'=> [
                'number' => [
                    'uuid' => $number->uuid
                ],
                'session' => [
                    
                ],
                'tracking_entity_uuid' => $trackingEntityUUID
            ]
        ]);

        //  Then make a third
        $onlineUser = factory(OnlineUser::class)->make([
            'user_agent' => $onlineUser->user_agent
        ]);
        $response = $this->json('POST', route('online-init'), [
            'http_referrer'  => $onlineUser->http_referrer,
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
        ], [
            'User-Agent' => $onlineUser->user_agent
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'session',
            'data'=> [
                'number' => [
                    'uuid' => $number->uuid
                ],
                'session' => [
                    
                ],
                'tracking_entity_uuid' => $trackingEntityUUID
            ]
        ]);

        $entities  = TrackingEntity::where('uuid', $trackingEntityUUID)->get();
        $this->assertTrue( count($entities) === 1 );

        $entity = $entities->first();
        $this->assertTrue(TrackingSession::where('tracking_entity_id', $entity->id)->count() === 3);
    }

    /**
     * Test that a new number will be issued even is the tracking id is provided when the number is no longer available
     * 
     * @group online
     */
    public function testPoolNumberChangedWhenOriginalTrackingNumberNoLongerAvailable()
    {
        
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config);
        $number      = $pool->phone_numbers->first();

        //  Make initial request
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
            'action' => 'session',
            'data'=> [
                'number' => [
                    'uuid' => $number->uuid
                ],
                'session' => []
            ],
            
        ]);

        $trackingEntityUUID = $response['data']['tracking_entity_uuid'];

        //  Make second request using tracking_entity_uuid
        $onlineUser = factory(OnlineUser::class)->make();
        $response = $this->json('POST', route('online-init'), [
            'http_referrer'  => $onlineUser->http_referrer,
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
            'tracking_entity_uuid'   => $trackingEntityUUID,
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'session',
            'data'=> [
                'number' => [
                    'uuid' => $number->uuid
                ],
                'session' => [
                    
                ],
                'tracking_entity_uuid' => $trackingEntityUUID
            ]
        ]);

        //  Remove the number
        $number->delete();

        //  Then make a third
        $onlineUser = factory(OnlineUser::class)->make();
        $response = $this->json('POST', route('online-init'), [
            'http_referrer'  => $onlineUser->http_referrer,
            'company_id'     => $company->id,
            'entry_url'      => $onlineUser->entry_url,
            'device_width'   => $onlineUser->device_width,
            'device_height'  => $onlineUser->device_height,
            'tracking_entity_uuid'   => $trackingEntityUUID,
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'action' => 'session',
            'data'=> [
                'number' => [
                    'uuid' => $pool->phone_numbers[1]->uuid
                ],
                'session' => [
                    
                ],
                'tracking_entity_uuid' => $trackingEntityUUID
            ]
        ]);
    }

     /**
     * Test logging ClickToCall and PageView Event
     * 
     * @group online
     */
    public function testLoggingSessionEvent()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config);
        $number      = $pool->phone_numbers->first();

        //  Init
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
            'action' => 'session',
            'data'=> [
                'number' => [
                    'uuid' => $number->uuid
                ],
                'session' => []
            ],
            
        ]);

        $sessionId          = $response['data']['session']['uuid'];
        $token              = $response['data']['session']['token'];

        //  Log Page View
        $content = str_random(1024);
        $response = $this->json('POST', route('online-event'), [
            'session_uuid' => $sessionId,
            'token'        => $token,
            'event_type'   => 'ClickToCall',
            'content'      => $content
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'message' => 'created'
        ]);

        $this->assertDatabaseHas('tracking_session_events', [
            'tracking_session_id' => TrackingSession::where('uuid', $sessionId)->first()->id,
            'event_type' => 'ClickToCall',
            'content'    => $content
        ]);

    }

    /**
     * Test logging invalid event type fails
     * 
     * @group online
     */
    public function testLoggingInvalidSessionEventFails()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config);
        $number      = $pool->phone_numbers->first();

        //  Init
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
            'action' => 'session',
            'data'=> [
                'number' => [
                    'uuid' => $number->uuid
                ],
                'session' => []
            ],
            
        ]);

        $sessionId   = $response['data']['session']['uuid'];
        $token       = $response['data']['session']['token'];

        //  Log Page View
        $content = str_random(1024);
        $response = $this->json('POST', route('online-event'), [
            'session_uuid' => $sessionId,
            'token'        => $token,
            'event_type'   => 'FooBar',
            'content'      => $content
        ]);

        $response->assertStatus(400);
        $response->assertJSONStructure([
            'error'
        ]);

        $this->assertDatabaseMissing('tracking_session_events', [
            'tracking_session_id' => TrackingSession::where('uuid', $sessionId)->first()->id,
            'event_type' => 'FooBar',
            'content'    => $content
        ]);

    }

     /**
     * Test logging event for expired session opens session back up
     * 
     * @group online
     */
    public function testLoggingEventForExpiredSessionReopensSession()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config);
        $number      = $pool->phone_numbers->first();

        //  Create a tracking session for this number 
        $session = $this->createTrackingSession($company, $number, $pool, [
            'ended_at' => now()
        ]);
        $this->assertNotNull($session->ended_at);

        //  Log Page View
        $content = str_random(1024);
        $response = $this->json('POST', route('online-event'), [
            'session_uuid' => $session->uuid,
            'token'        => $session->token,
            'event_type'   => 'PageView',
            'content'      => $content
        ]);

        $response->assertStatus(201);
        
        $updateSession = TrackingSession::find($session->id);
            
        $this->assertTrue($updateSession->created_at->format('Y-m-d H:i:s.u') != $updateSession->last_heartbeat_at->format('Y-m-d H:i:s.u'));
        $this->assertNull($updateSession->ended_at);
    }

    /**
     * Test updating session heartbeat
     * 
     * @group online
     */
    public function testHeartbeat()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config);
        $number      = $pool->phone_numbers->first();

        //  Init
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
            'action' => 'session',
            'data'=> [
                'number' => [
                    'uuid' => $number->uuid
                ],
                'session' => []
            ], 
        ]);
        $sessionId   = $response['data']['session']['uuid'];
        $token       = $response['data']['session']['token'];

        //  Make sure times match at first
        $session = TrackingSession::where('uuid', $sessionId)->first();
        $this->assertTrue($session->created_at->format('Y-m-d H:i:s.u') == $session->last_heartbeat_at->format('Y-m-d H:i:s.u') );

        $response = $this->json('POST', route('online-heartbeat'), [
            'session_uuid' => $sessionId,
            'token'        => $token,
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'I love you too'
        ]);

        //  Make sure the times no longer match  
        $session = TrackingSession::where('uuid', $sessionId)->first();
        $this->assertTrue($session->created_at != $session->last_heartbeat_at);
    }

    /**
     * Test heartbeat opens session back up
     * 
     * @group online
     */
    public function testHeartbeatReopensSession()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $pool        = $this->createPhoneNumberPool($company, $config);
        $number      = $pool->phone_numbers->first();

        //  Create a tracking session for this number 
        $session = $this->createTrackingSession($company, $number, $pool, [
            'ended_at' => now()
        ]);
        $this->assertNotNull($session->ended_at);

        $response = $this->json('POST', route('online-heartbeat'), [
            'session_uuid' => $session->uuid,
            'token'        => $session->token,
        ]);

        $response->assertStatus(200);
        
        $updateSession = TrackingSession::find($session->id);
            
        $this->assertTrue($updateSession->created_at->format('Y-m-d H:i:s.u') != $updateSession->last_heartbeat_at->format('Y-m-d H:i:s.u'));
        $this->assertNull($updateSession->ended_at);
    }
}
