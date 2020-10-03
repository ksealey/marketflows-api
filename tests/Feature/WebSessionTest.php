<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use \App\Models\Company\PhoneNumber;
use App\Models\Company\KeywordTrackingPool;
use Tests\Models\Browser;

class WebSessionTest extends TestCase
{
    use \Tests\CreatesAccount, WithFaker;

    /**
     * Test a user attempting to get a web session, but swapping fails
     *
     * @group web-sessions
     */
    public function testStartSessionWhenSwapMatchFails()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $failedRules = json_encode([
            'targets' => [
                str_replace('+', '', $this->faker()->e164PhoneNumber)
            ],
            'browser_types' => ['CHROME'],// Only on chrome
            'device_types'  => ['ALL'], 
            'inclusion_rules' => [
                [
                    'rules' => [
                        [
                            'type' => 'ALL'
                        ]
                    ]
                ]
            ],
            'exclusion_rules' => []
        ]);

        //  Pool w/numbers
        $pool = factory(KeywordTrackingPool::class)->create([
            'account_id'                => $company->account_id,
            'company_id'                => $company->id,
            'phone_number_config_id'    => $config->id,
            'created_by'                => $this->user->id,
            'swap_rules'                => $failedRules
        ]);
        factory(PhoneNumber::class, 10)->create([
            'category'     => 'ONLINE',
            'sub_category' => 'WEBSITE',
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'keyword_tracking_pool_id' => $pool->id,
            'created_by' => $this->user->id,
            'swap_rules' => $failedRules
        ]);

        // Detached numbers
        factory(PhoneNumber::class, 10)->create([
            'category'     => 'ONLINE',
            'sub_category' => 'WEBSITE',
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by' => $this->user->id,
            'swap_rules' => $failedRules
        ]);

        $browser = factory(Browser::class)->make([
            'user_agent' => $this->faker()->firefox // Request as firefox so it fails against chrome match
        ]);

        $response = $this->post(route('web-start-session', [
            'device_width'  => $browser->device_width,
            'device_height' => $browser->device_height,
            'user_agent'    => $browser->user_agent,
            'landing_url'   => $browser->landing_url,
            'http_referrer' => $browser->http_referrer,
            'company_id'    => $company->id
        ]));

        $response->assertCookieMissing('session_uuid');
        $response->assertCookieMissing('session_token');
        $response->assertCookieMissing('phone_uuid');
        $response->assertCookieMissing('phone_country_code');
        $response->assertCookieMissing('phone_number');
        $response->assertCookieMissing('swapping_targets');
        $response->assertCookie('guuid');
        $response->assertCookie('init', 1);

        $response->assertJSON([
            'session'  => null,
            'swapping' => null,
            'phone'    => null
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test a user attempting to get a web session, and swapping fails for pool but passes for phone number
     *
     * @group web-sessions
     */
    public function testStartSessionWhenPoolSwapMatchFailsAndNumberPasses()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);
        $failedRules = json_encode([
            'targets' => [
                str_replace('+', '', $this->faker()->e164PhoneNumber)
            ],
            'browser_types' => ['CHROME'],// Only on chrome
            'device_types'  => ['ALL'], 
            'inclusion_rules' => [ [ 'rules' => [ [ 'type' => 'ALL' ] ] ] ],
            'exclusion_rules' => []
        ]);

         //  Pool w/numbers
         $pool = factory(KeywordTrackingPool::class)->create([
            'account_id'                => $company->account_id,
            'company_id'                => $company->id,
            'phone_number_config_id'    => $config->id,
            'created_by'                => $this->user->id,
            'swap_rules'                => $failedRules
        ]);
        factory(PhoneNumber::class, 10)->create([
            'category'     => 'ONLINE',
            'sub_category' => 'WEBSITE',
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'keyword_tracking_pool_id' => $pool->id,
            'created_by' => $this->user->id,
            'swap_rules' => $failedRules
        ]);

        // Detached numbers
        $phoneNumber = factory(PhoneNumber::class, 10)->create([
            'category'     => 'ONLINE',
            'sub_category' => 'WEBSITE',
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by' => $this->user->id
        ])->last();
        $browser = factory(Browser::class)->make([
            'user_agent' => $this->faker()->firefox // Request as firefox so it fails against chrome match
        ]);

        $response = $this->post(route('web-start-session', [
            'device_width'  => $browser->device_width,
            'device_height' => $browser->device_height,
            'user_agent'    => $browser->user_agent,
            'landing_url'   => $browser->landing_url,
            'http_referrer' => $browser->http_referrer,
            'company_id'    => $company->id
        ]));

        $response->assertJSON([
            'session' => null,
            'swapping' => [
                'targets' => $phoneNumber->swap_rules->targets,
            ],
            'phone' => [
                'uuid'          => $phoneNumber->uuid,
                'country_code'  => $phoneNumber->country_code,
                'number'        => $phoneNumber->number
            ]
        ]);

        $response->assertStatus(200);

        $response->assertCookieMissing('session_uuid');
        $response->assertCookieMissing('session_token');
        $response->assertCookie('phone_uuid', $phoneNumber->uuid);
        $response->assertCookie('phone_country_code', $phoneNumber->country_code);
        $response->assertCookie('phone_number', $phoneNumber->number);
        $response->assertCookie('swapping_targets', json_encode($phoneNumber->swap_rules->targets));
        $response->assertCookie('init', 1);
        $response->assertCookie('guuid');

        $this->assertDatabaseHas('phone_numbers', [
            'id'                => $phoneNumber->id,
            'total_assignments' => 1
        ]);

    }

    /**
     * Test a user getting a keyword tracking pool session
     *
     * @group web-sessions
     */
    public function testStartSessionWhenPoolSwapMatchPasses()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);

        //  Pool w/numbers
        $pool = factory(KeywordTrackingPool::class)->create([
            'account_id'                => $company->account_id,
            'company_id'                => $company->id,
            'phone_number_config_id'    => $config->id,
            'created_by'                => $this->user->id,
        ]);
        $poolNumbers = factory(PhoneNumber::class, 5)->create([
            'category'     => 'ONLINE',
            'sub_category' => 'WEBSITE',
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'keyword_tracking_pool_id' => $pool->id,
            'created_by' => $this->user->id,
            'last_assigned_at' => null,
            'created_at' => null
        ]);

        // Detached numbers
        $phoneNumbers = factory(PhoneNumber::class, 10)->create([
            'category'     => 'ONLINE',
            'sub_category' => 'WEBSITE',
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by' => $this->user->id
        ]);
        
        //  Test 3 rounds of number rotations
        for( $i = 0; $i < 3; $i++){
            $poolNumbers->each(function($phoneNumber) use($pool, $company, $i){
                $browser = factory(Browser::class)->make();
                $response = $this->post(route('web-start-session', [
                    'device_width'  => $browser->device_width,
                    'device_height' => $browser->device_height,
                    'user_agent'    => $browser->user_agent,
                    'landing_url'   => $browser->landing_url,
                    'http_referrer' => $browser->http_referrer,
                    'company_id'    => $company->id
                ]));
                $response->assertJSON([
                    'session' => [
                        'ktp_id' => $pool->id,
                    ],
                    'swapping' => [
                        'targets' => $pool->swap_rules->targets,
                    ],
                    'phone' => [
                        'uuid'          => $phoneNumber->uuid,
                        'country_code'  => $phoneNumber->country_code,
                        'number'        => $phoneNumber->number
                    ]
                ]);

                $response->assertStatus(200);

                $response->assertCookie('guuid', $response['guuid']);
                $response->assertCookie('session_uuid', $response['session']['uuid']);
                $response->assertCookie('session_token', $response['session']['token']);
                $response->assertCookie('phone_uuid', $phoneNumber->uuid);
                $response->assertCookie('phone_country_code', $phoneNumber->country_code);
                $response->assertCookie('phone_number', $phoneNumber->number);
                $response->assertCookie('swapping_targets', json_encode($pool->swap_rules->targets));
                $response->assertCookie('init', 1);

                $this->assertDatabaseHas('phone_numbers', [
                    'id'                => $phoneNumber->id,
                    'total_assignments' => $i+1
                ]);
            });
        }
    }

     /**
     * Test a user getting a keyword tracking pool session, then trying to get another session gets the same session
     *
     * @group web-sessions
     */
    public function testStartSessionWithExistingSessionReturnsSameSession()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);

        //  Pool w/numbers
        $pool = factory(KeywordTrackingPool::class)->create([
            'account_id'                => $company->account_id,
            'company_id'                => $company->id,
            'phone_number_config_id'    => $config->id,
            'created_by'                => $this->user->id,
        ]);
        $poolNumbers = factory(PhoneNumber::class, 10)->create([
            'category'     => 'ONLINE',
            'sub_category' => 'WEBSITE',
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'keyword_tracking_pool_id' => $pool->id,
            'created_by' => $this->user->id,
            'last_assigned_at' => null,
            'created_at' => null
        ]);

        // Detached numbers
        $phoneNumbers = factory(PhoneNumber::class, 10)->create([
            'category'     => 'ONLINE',
            'sub_category' => 'WEBSITE',
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by' => $this->user->id
        ]);

        //
        //  Make initial request
        //
        $phoneNumber = $poolNumbers->first();
        $browser = factory(Browser::class)->make();
        $response = $this->post(route('web-start-session', [
            'device_width'  => $browser->device_width,
            'device_height' => $browser->device_height,
            'user_agent'    => $browser->user_agent,
            'landing_url'   => $browser->landing_url,
            'http_referrer' => $browser->http_referrer,
            'company_id'    => $company->id
        ]));
        $response->assertJSON([
            'session' => [
                'ktp_id' => $pool->id,
            ],
            'swapping' => [
                'targets' => $pool->swap_rules->targets
            ],
            'phone' => [
                'uuid'          => $phoneNumber->uuid,
                'country_code'  => $phoneNumber->country_code,
                'number'        => $phoneNumber->number
            ]
        ]);

        $response->assertStatus(200);
        $response->assertCookie('guuid', $response['guuid']);
        $response->assertCookie('session_uuid', $response['session']['uuid']);
        $response->assertCookie('session_token', $response['session']['token']);
        $response->assertCookie('phone_uuid', $phoneNumber->uuid);
        $response->assertCookie('phone_country_code', $phoneNumber->country_code);
        $response->assertCookie('phone_number', $phoneNumber->number);
        $response->assertCookie('swapping_targets', json_encode($pool->swap_rules->targets));
        $response->assertCookie('init', 1);

        //
        //  Make second request
        //
        $response = $this->withCookies([
            'guuid'         => $response['guuid'],
            'session_uuid'  => $response['session']['uuid'],
            'session_token' => $response['session']['token']
        ])->post(route('web-start-session', [
            'device_width'  => $browser->device_width,
            'device_height' => $browser->device_height,
            'user_agent'    => $browser->user_agent,
            'landing_url'   => $browser->landing_url,
            'http_referrer' => $browser->http_referrer,
            'company_id'    => $company->id
        ]));

        $response->assertJSON([
            'session' => [
                'ktp_id' => $pool->id,
            ],
            'swapping' => [
                'targets' => $pool->swap_rules->targets
            ],
            'phone' => [
                'uuid'          => $phoneNumber->uuid,
                'country_code'  => $phoneNumber->country_code,
                'number'        => $phoneNumber->number
            ]
        ]);
    }

    /**
     * Test collecting data for a session
     *
     * @group web-sessions
     */
    public function testCollect()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);

        //  Pool w/numbers
        $pool = factory(KeywordTrackingPool::class)->create([
            'account_id'                => $company->account_id,
            'company_id'                => $company->id,
            'phone_number_config_id'    => $config->id,
            'created_by'                => $this->user->id,
        ]);
        $poolNumbers = factory(PhoneNumber::class, 5)->create([
            'category'     => 'ONLINE',
            'sub_category' => 'WEBSITE',
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'keyword_tracking_pool_id' => $pool->id,
            'created_by' => $this->user->id,
            'last_assigned_at' => null,
            'created_at' => null
        ]);
       
        $browser = factory(Browser::class)->make();
        $response = $this->post(route('web-start-session', [
            'device_width'  => $browser->device_width,
            'device_height' => $browser->device_height,
            'user_agent'    => $browser->user_agent,
            'landing_url'   => $browser->landing_url,
            'http_referrer' => $browser->http_referrer,
            'company_id'    => $company->id
        ]));

        $url = $this->faker()->url;
        $sessionUUID  = $response['session']['uuid'];
        $sessionToken = $response['session']['token'];
        $response = $this->withCookies([
            'session_uuid'  => $sessionUUID,
            'session_token' => $sessionToken
        ])->post(route('web-collect'), [
            'url' => $url
        ]);
        $response->assertJSON([
            'message' => 'OK'
        ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('keyword_tracking_pool_sessions', [
            'uuid'     => $sessionUUID,
            'last_url' => $url
        ]);
    }

    /**
     * Test ending a session
     *
     * @group web-sessions--
     */
    public function testEndSession()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company);

        //  Pool w/numbers
        $pool = factory(KeywordTrackingPool::class)->create([
            'account_id'                => $company->account_id,
            'company_id'                => $company->id,
            'phone_number_config_id'    => $config->id,
            'created_by'                => $this->user->id,
        ]);
        $poolNumbers = factory(PhoneNumber::class, 5)->create([
            'category'     => 'ONLINE',
            'sub_category' => 'WEBSITE',
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'keyword_tracking_pool_id' => $pool->id,
            'created_by' => $this->user->id,
            'last_assigned_at' => null,
            'created_at' => null
        ]);
       
        $browser = factory(Browser::class)->make();
        $response = $this->post(route('web-start-session', [
            'device_width'  => $browser->device_width,
            'device_height' => $browser->device_height,
            'user_agent'    => $browser->user_agent,
            'landing_url'   => $browser->landing_url,
            'http_referrer' => $browser->http_referrer,
            'company_id'    => $company->id
        ]));

        $sessionUUID  = $response['session']['uuid'];
        $sessionToken = $response['session']['token'];

        $this->assertDatabaseHas('keyword_tracking_pool_sessions', [
            'uuid'     => $sessionUUID,
            'ended_at' => null
        ]);

        $response = $this->withCookies([
            'session_uuid'  => $sessionUUID,
            'session_token' => $sessionToken
        ])->post(route('web-end-session'));

        $response->assertJSON([
            'message' => 'OK'
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('keyword_tracking_pool_sessions', [
            'uuid'     => $sessionUUID,
            'ended_at' => null
        ]);
    }
}
