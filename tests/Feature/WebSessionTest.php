<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use App\Models\Company\Webhook;
use App\Models\Company\Call;
use App\Models\Company\PhoneNumber;
use App\Models\Company\KeywordTrackingPool;
use App\Models\Company\KeywordTrackingPoolSession;
use App\Events\Company\CallEvent;
use App\Services\TranscribeService;
use Twilio\Rest\Client as TwilioClient;
use App\Jobs\ProcessCallRecordingJob;
use Tests\Models\TwilioIncomingCall;
use Tests\Models\Browser;
use Queue;
use Storage;

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
     * @group web-sessions
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



    /**
     * Test the full flow of a user making a call to a detached number
     * 
     * @group web-sessions
     */
    public function testEntireFlowForDetachedNumber()
    {
        Event::fake();
        Storage::fake();

        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company, [
            'recording_enabled'     => 1,
            'transcription_enabled' => 1
        ]);

        $phoneNumber = factory(PhoneNumber::class, 10)->create([
            'account_id'             => $company->account_id,
            'company_id'             => $company->id,
            'phone_number_config_id' => $config->id,
            'category'               => 'ONLINE',
            'sub_category'           => 'WEBSITE',
            'created_by'             => $this->user->id
        ])->last();

        for( $i = 0; $i < 5; $i++ ){
            $browser  = factory(Browser::class)->make();
            $response = $this->post(route('web-start-session', [
                'device_width'  => $browser->device_width,
                'device_height' => $browser->device_height,
                'user_agent'    => $browser->user_agent,
                'landing_url'   => $browser->landing_url,
                'http_referrer' => $browser->http_referrer,
                'company_id'    => $company->id
            ]));
            $response->assertStatus(200);
            $this->assertDatabaseHas('phone_numbers', [
                'id'                => $phoneNumber->id,
                'total_assignments' => $i + 1,
            ]);

            $incomingCall = factory(TwilioIncomingCall::class)->make([
                'To' => $phoneNumber->e164Format()
            ]);
            $response = $this->post(route('incoming-call'), $incomingCall->toArray());
            $call     = Call::where('external_id', $incomingCall->CallSid)->first();
            $this->assertNotNull($call);
            $this->assertDatabaseHas('calls', [
                'id'                            => $call->id,
                'external_id'                   => $incomingCall->CallSid,
                'phone_number_id'               => $phoneNumber->id,
                'status'                        => 'Ringing',
                'forwarded_to'                  => $config->forwardToPhoneNumber(),
                'keyword_tracking_pool_id'      => null,
                'keyword_tracking_pool_name'    => null,
                'source'                        => $phoneNumber->source,
                'content'                       => $phoneNumber->content,
                'campaign'                      => $phoneNumber->campaign,
                'medium'                        => $phoneNumber->medium,
            ]);
            Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
                return $company->id === $event->call->company_id && $event->name === Webhook::ACTION_CALL_START;
            });

            //  Update the call
            $incomingCall->CallStatus = 'Answered';
            $response = $this->post(route('incoming-call-status-changed'), $incomingCall->toArray());
            $this->assertDatabaseHas('calls', [
                'id'                            => $call->id,
                'external_id'                   => $incomingCall->CallSid,
                'phone_number_id'               => $phoneNumber->id,
                'status'                        => 'Answered',
                'forwarded_to'                  => $config->forwardToPhoneNumber(),
                'keyword_tracking_pool_id'      => null,
                'keyword_tracking_pool_name'    => null,
                'source'                        => $phoneNumber->source,
                'content'                       => $phoneNumber->content,
                'campaign'                      => $phoneNumber->campaign,
                'medium'                        => $phoneNumber->medium,
            ]);

            //  End the call
            $incomingCall->CallStatus   = 'Completed';
            $incomingCall->CallDuration = mt_rand(99,999); 
            $response = $this->post(route('incoming-call-status-changed'), $incomingCall->toArray());
            $this->assertDatabaseHas('calls', [
                'id'                            => $call->id,
                'external_id'                   => $incomingCall->CallSid,
                'phone_number_id'               => $phoneNumber->id,
                'status'                        => 'Completed',
                'duration'                      => $incomingCall->CallDuration,
                'forwarded_to'                  => $config->forwardToPhoneNumber(),
                'keyword_tracking_pool_id'      => null,
                'keyword_tracking_pool_name'    => null,
                'source'                        => $phoneNumber->source,
                'content'                       => $phoneNumber->content,
                'campaign'                      => $phoneNumber->campaign,
                'medium'                        => $phoneNumber->medium,
            ]);

            Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
                return $company->id === $event->call->company_id && $event->name === Webhook::ACTION_CALL_END;
            });

            $url                = $this->faker()->url;
            $duration           = $incomingCall->CallDuration;
            $recordingSid       = str_random(40);
            $recordingContent   = random_bytes(9999);
            $recordingPath      = 'accounts/' . $call->account_id . '/companies/' . $call->company_id . '/recordings/Call-' . $call->id . '.mp3';
            $transcriptionPath  = 'accounts/' . $call->account_id . '/companies/' . $call->company_id . '/transcriptions/Transcription-' . $call->id . '.json';
            
            $this->mock('HTTPClient', function($mock) use($url, $recordingContent){
                $mock->shouldReceive('request')
                     ->with('GET', $url . '.mp3')
                     ->andReturn($mock);
    
                $mock->shouldReceive('getBody')
                     ->andReturn($recordingContent);
            });
    
            $this->mock(TwilioClient::class, function($mock) use($recordingSid){
                $mock->shouldReceive('recordings')
                     ->with($recordingSid)
                     ->andReturn($mock)
                     ->once();
                
                $mock->shouldReceive('delete')
                     ->once();
            });

            $this->mock(TranscribeService::class, function($mock) use($recordingContent){
                $jobId   = str_random(10);
                $fileUrl = $this->faker()->url;
                $mock->shouldReceive('startTranscription')
                    ->andReturn($jobId)
                    ->once();
                    
                $mock->shouldReceive('waitForUrl')
                    ->with($jobId)
                    ->andReturn($fileUrl)
                    ->once();

                $mock->shouldReceive('downloadFromUrl')
                    ->with($fileUrl)
                    ->andReturn($recordingContent)
                    ->once();

                $mock->shouldReceive('transformContent')
                    ->with($recordingContent)
                    ->andReturn([
                        'hello_world'
                    ])
                    ->once();

                $mock->shouldReceive('deleteTranscription')
                    ->with($jobId)
                    ->once();
            });

            $response = $this->post(route('incoming-call-recording-available', [
                'AccountSid'        => config('services.twilio.sid'),
                'CallSid'           => $call->external_id,
                'RecordingSid'      => $recordingSid,
                'RecordingUrl'      => $url,
                'RecordingDuration' => $duration
            ]));

            Storage::assertExists($recordingPath);
            Storage::assertExists($transcriptionPath);
            
            $this->assertDatabaseHas('call_recordings', [
                'call_id'               => $call->id,
                'external_id'           => $recordingSid,
                'path'                  => $recordingPath,
                'transcription_path'    => $transcriptionPath,
                'duration'              => $duration
            ]);
        }
    }

     /**
     * Test the full flow of a user making a call to a phone number pool
     * 
     * @group web-sessions
     */
    public function testEntireFlowForPhoneNumberPool()
    {
        Event::fake();
        Storage::fake();

        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company, [
            'greeting_enabled'      => 1,
            'greeting_message_type' => 'TEXT',
            'greeting_message'      => 'Hello World',
            'keypress_enabled'      => 1,
            'keypress_message_type' => 'TEXT',
            'keypress_message'      => 'Invalid Entry. Please press 1 to continue.',
            'keypress_attempts'     => 3,
            'keypress_key'          => 1,
            'recording_enabled'     => 1,
            'transcription_enabled' => 1
        ]);

        $pool = factory(KeywordTrackingPool::class)->create([
            'account_id'                => $company->account_id,
            'company_id'                => $company->id,
            'phone_number_config_id'    => $config->id,
            'created_by'                => $this->user->id
        ]);

        $phoneNumbers = factory(PhoneNumber::class, 5)->create([
            'account_id'             => $company->account_id,
            'company_id'             => $company->id,
            'phone_number_config_id' => $config->id,
            'keyword_tracking_pool_id' => $pool->id,
            'category'               => 'ONLINE',
            'sub_category'           => 'WEBSITE',
            'created_by'             => $this->user->id
        ]);

        for( $i = 0; $i < 2; $i++ ){
            foreach($phoneNumbers as $idx => $phoneNumber){
                $phoneNumber = PhoneNumber::find($phoneNumber->id);

                $params = [
                    'utm_source'    => str_random(40),
                    'utm_content'   => str_random(40),
                    'utm_campaign'  => str_random(40),
                    'utm_medium'    => str_random(40),
                    'utm_term'  => str_random(40),
                ];
                $browser  = factory(Browser::class)->make([
                    'landing_url' => $this->faker()->url . '?' . http_build_query($params) 
                ]);
                $response = $this->withHeaders([
                    'User-Agent' => $browser->user_agent
                ])->withCookies([
                    'guuid'         => '',
                    'session_uuid'  => '',
                    'session_token' => ''
                ])->post(route('web-start-session', [
                    'device_width'  => $browser->device_width,
                    'device_height' => $browser->device_height,
                    'landing_url'   => $browser->landing_url,
                    'http_referrer' => $browser->http_referrer,
                    'company_id'    => $company->id
                ]));

                $response->assertStatus(200);
                $response->assertJSONStructure([
                    'guuid',
                    'session' => [
                        'ktp_id',
                        'uuid',
                        'token',
                    ],
                    'phone' => [
                        'uuid',
                        'number',
                        'country_code'
                    ],
                    'swapping' => [
                        'targets'
                    ]
                ]);

                $gUUID        = $response['guuid'];
                $sessionUUID  = $response['session']['uuid'];
                $sessionToken = $response['session']['token'];
                $this->assertDatabaseHas('phone_numbers', [
                    'id'                => $phoneNumber->id,
                    'total_assignments' => $phoneNumber->total_assignments + 1,
                ]);
                
                $this->assertDatabaseHas('keyword_tracking_pool_sessions', [
                    'guuid'                    => $gUUID,
                    'uuid'                     => $sessionUUID,
                    'contact_id'               => null,
                    'keyword_tracking_pool_id' => $pool->id,
                    'phone_number_id'          => $phoneNumber->id,
                    'landing_url'              => $browser->landing_url,
                    'last_url'                 => $browser->landing_url,
                    'device_width'             => $browser->device_width,
                    'device_height'            => $browser->device_height,
                    'ended_at'                 => null
                ]);

                $lastURL = $this->faker()->url;
                $response = $this->withCookies([
                    'session_uuid'  => $sessionUUID,
                    'session_token' => $sessionToken,
                ])->post(route('web-collect', [
                    'url'           => $lastURL
                ]));
                $response->assertJSON([
                    'message' => 'OK'
                ]);
                $response->assertStatus(200);

                $this->assertDatabaseHas('keyword_tracking_pool_sessions', [
                    'guuid'                    => $gUUID,
                    'uuid'                     => $sessionUUID,
                    'keyword_tracking_pool_id' => $pool->id,
                    'phone_number_id'          => $phoneNumber->id,
                    'landing_url'              => $browser->landing_url,
                    'last_url'                 => $lastURL,
                    'device_width'             => $browser->device_width,
                    'device_height'            => $browser->device_height,
                    'ended_at'                 => null
                ]);
                
                $session      = KeywordTrackingPoolSession::where('uuid', $sessionUUID)->first();
                $incomingCall = factory(TwilioIncomingCall::class)->make([
                    'To'   => $phoneNumber->e164Format()
                ]);
                
                $response = $this->post(route('incoming-call'), $incomingCall->toArray());
                
                $call = Call::where('external_id', $incomingCall->CallSid)->first();
                
                $this->assertNotNull($call);
               
                $this->assertDatabaseHas('calls', [
                    'id'                            => $call->id,
                    'external_id'                   => $incomingCall->CallSid,
                    'phone_number_id'               => $phoneNumber->id,
                    'status'                        => 'Ringing',
                    'forwarded_to'                  => $config->forwardToPhoneNumber(),
                    'keyword_tracking_pool_id'      => $pool->id,
                    'keyword_tracking_pool_name'    => $pool->name,
                    'keyword_tracking_pool_session_id' => $session->id,
                    'source'                        => $session->getSource($company->source_param, $company->source_referrer_when_empty),
                    'medium'                        => $session->getMedium($company->medium_param),
                    'content'                       => $session->getContent($company->content_param),
                    'campaign'                      => $session->getCampaign($company->campaign_param),
                    'keyword'                       => $session->getKeyword($company->keyword_param),
                    'is_paid'                       => $session->getIsPaid($company->medium_param),
                    'is_organic'                    => $session->getIsOrganic($company->medium_param),
                    'is_direct'                     => $session->getIsDirect(),
                    'is_referral'                   => $session->getIsReferral(),
                ]);
                
                Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
                    return $company->id === $event->call->company_id && $event->name === Webhook::ACTION_CALL_START;
                });
                
                //  Update the call
                $incomingCall->CallStatus = 'Answered';
                $response = $this->post(route('incoming-call-status-changed'), $incomingCall->toArray());
                $this->assertDatabaseHas('calls', [
                    'id'                            => $call->id,
                    'external_id'                   => $incomingCall->CallSid,
                    'phone_number_id'               => $phoneNumber->id,
                    'status'                        => 'Answered',
                    'forwarded_to'                  => $config->forwardToPhoneNumber(),
                    'keyword_tracking_pool_id'      => $pool->id,
                    'keyword_tracking_pool_name'    => $pool->name,
                    'keyword_tracking_pool_session_id' => $session->id,
                    'source'                        => $session->getSource($company->source_param, $company->source_referrer_when_empty),
                    'medium'                        => $session->getMedium($company->medium_param),
                    'content'                       => $session->getContent($company->content_param),
                    'campaign'                      => $session->getCampaign($company->campaign_param),
                    'keyword'                       => $session->getKeyword($company->keyword_param),
                    'is_paid'                       => $session->getIsPaid($company->medium_param),
                    'is_organic'                    => $session->getIsOrganic($company->medium_param),
                    'is_direct'                     => $session->getIsDirect(),
                    'is_referral'                   => $session->getIsReferral(),
                ]);

                //  End the call
                $incomingCall->CallStatus   = 'Completed';
                $incomingCall->CallDuration = mt_rand(99,999); 
                $response = $this->post(route('incoming-call-status-changed'), $incomingCall->toArray());
                $this->assertDatabaseHas('calls', [
                    'id'                            => $call->id,
                    'external_id'                   => $incomingCall->CallSid,
                    'phone_number_id'               => $phoneNumber->id,
                    'status'                        => 'Completed',
                    'forwarded_to'                  => $config->forwardToPhoneNumber(),
                    'keyword_tracking_pool_id'      => $pool->id,
                    'keyword_tracking_pool_name'    => $pool->name,
                    'keyword_tracking_pool_session_id' => $session->id,
                    'source'                        => $session->getSource($company->source_param, $company->source_referrer_when_empty),
                    'medium'                        => $session->getMedium($company->medium_param),
                    'content'                       => $session->getContent($company->content_param),
                    'campaign'                      => $session->getCampaign($company->campaign_param),
                    'keyword'                       => $session->getKeyword($company->keyword_param),
                    'is_paid'                       => $session->getIsPaid($company->medium_param),
                    'is_organic'                    => $session->getIsOrganic($company->medium_param),
                    'is_direct'                     => $session->getIsDirect(),
                    'is_referral'                   => $session->getIsReferral(),
                ]);

                Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
                    return $company->id === $event->call->company_id && $event->name === Webhook::ACTION_CALL_END;
                });
                
                $url                = $this->faker()->url;
                $duration           = $incomingCall->CallDuration;
                $recordingSid       = str_random(40);
                $recordingContent   = random_bytes(9999);
                $recordingPath      = 'accounts/' . $call->account_id . '/companies/' . $call->company_id . '/recordings/Call-' . $call->id . '.mp3';
                $transcriptionPath  = 'accounts/' . $call->account_id . '/companies/' . $call->company_id . '/transcriptions/Transcription-' . $call->id . '.json';
                
                $this->mock('HTTPClient', function($mock) use($url, $recordingContent){
                    $mock->shouldReceive('request')
                        ->with('GET', $url . '.mp3')
                        ->andReturn($mock);
        
                    $mock->shouldReceive('getBody')
                        ->andReturn($recordingContent);
                });
        
                $this->mock(TwilioClient::class, function($mock) use($recordingSid){
                    $mock->shouldReceive('recordings')
                        ->with($recordingSid)
                        ->andReturn($mock)
                        ->once();
                    
                    $mock->shouldReceive('delete')
                        ->once();
                });

                $this->mock(TranscribeService::class, function($mock) use($recordingContent){
                    $jobId   = str_random(10);
                    $fileUrl = $this->faker()->url;
                    $mock->shouldReceive('startTranscription')
                        ->andReturn($jobId)
                        ->once();
                        
                    $mock->shouldReceive('waitForUrl')
                        ->with($jobId)
                        ->andReturn($fileUrl)
                        ->once();

                    $mock->shouldReceive('downloadFromUrl')
                        ->with($fileUrl)
                        ->andReturn($recordingContent)
                        ->once();

                    $mock->shouldReceive('transformContent')
                        ->with($recordingContent)
                        ->andReturn([
                            'hello_world'
                        ])
                        ->once();

                    $mock->shouldReceive('deleteTranscription')
                        ->with($jobId)
                        ->once();
                });

                $response = $this->post(route('incoming-call-recording-available', [
                    'AccountSid'        => config('services.twilio.sid'),
                    'CallSid'           => $call->external_id,
                    'RecordingSid'      => $recordingSid,
                    'RecordingUrl'      => $url,
                    'RecordingDuration' => $duration
                ]));

                Storage::assertExists($recordingPath);
                Storage::assertExists($transcriptionPath);
                
                $this->assertDatabaseHas('call_recordings', [
                    'call_id'               => $call->id,
                    'external_id'           => $recordingSid,
                    'path'                  => $recordingPath,
                    'transcription_path'    => $transcriptionPath,
                    'duration'              => $duration
                ]);

                //  Test ending the session
                $this->assertDatabaseHas('keyword_tracking_pool_sessions', [
                    'id'       => $session->id,
                    'ended_at' => null
                ]);
                
                $response = $this->withCookies([
                    'session_uuid'  => $sessionUUID,
                    'session_token' => $sessionToken,
                ])->post(route('web-end-session'));
                $response->assertStatus(200);
                
                $this->assertDatabaseMissing('keyword_tracking_pool_sessions', [
                    'id'       => $session->id,
                    'ended_at' => null
                ]);
                
                PhoneNumber::where('id', $phoneNumber->id)->update([
                    'last_assigned_at' => null
                ]);
                
                //
                //  Start a new session and make sure it links up the contact
                //
                $params = [
                    'utm_source'    => str_random(40),
                    'utm_content'   => str_random(40),
                    'utm_campaign'  => str_random(40),
                    'utm_medium'    => str_random(40),
                    'utm_term'  => str_random(40),
                ];
                $browser  = factory(Browser::class)->make([
                    'landing_url' => $this->faker()->url . '?' . http_build_query($params) 
                ]); 

                $response = $this->withHeaders([
                    'User-Agent' => $browser->user_agent
                ])->withCookies([
                    'guuid'         => $gUUID,
                ])->post(route('web-start-session', [
                    'device_width'  => $browser->device_width,
                    'device_height' => $browser->device_height,
                    'landing_url'   => $browser->landing_url,
                    'http_referrer' => $browser->http_referrer,
                    'company_id'    => $company->id
                ]));
                
                $response->assertStatus(200);
                $response->assertJSONStructure([
                    'guuid',
                    'session' => [
                        'ktp_id',
                        'uuid',
                        'token',
                    ],
                    'phone' => [
                        'uuid',
                        'number',
                        'country_code'
                    ],
                    'swapping' => [
                        'targets'
                    ]
                ]);

                $sessionUUID  = $response['session']['uuid'];
                $sessionToken = $response['session']['token'];
                $session      = KeywordTrackingPoolSession::where('uuid', $sessionUUID)->first();
                $this->assertNotNull($session->contact_id); // make sure it could find the contact using the uuid
                $this->assertDatabaseHas('keyword_tracking_pool_sessions', [
                    'guuid'                    => $gUUID,
                    'uuid'                     => $sessionUUID,
                    'keyword_tracking_pool_id' => $pool->id,
                    'phone_number_id'          => $phoneNumber->id,
                    'landing_url'              => $browser->landing_url,
                    'last_url'                 => $browser->landing_url,
                    'device_width'             => $browser->device_width,
                    'device_height'            => $browser->device_height,
                    'ended_at'                 => null
                ]);

                $this->assertDatabaseHas('phone_numbers', [
                    'id'                => $phoneNumber->id,
                    'total_assignments' => $phoneNumber->total_assignments + 2,
                ]);

                $lastURL = $this->faker()->url;
                $response = $this->withCookies([
                    'session_uuid'  => $sessionUUID,
                    'session_token' => $sessionToken,
                ])->post(route('web-collect', [
                    'url'           => $lastURL
                ]));
                $response->assertJSON([
                    'message' => 'OK'
                ]);
                $response->assertStatus(200);

                $this->assertDatabaseHas('keyword_tracking_pool_sessions', [
                    'guuid'                    => $gUUID,
                    'uuid'                     => $sessionUUID,
                    'keyword_tracking_pool_id' => $pool->id,
                    'phone_number_id'          => $phoneNumber->id,
                    'landing_url'              => $browser->landing_url,
                    'last_url'                 => $lastURL,
                    'device_width'             => $browser->device_width,
                    'device_height'            => $browser->device_height,
                    'ended_at'                 => null
                ]);
                
                $incomingCall = factory(TwilioIncomingCall::class)->make([
                    'To'   => $phoneNumber->e164Format(),
                    'From' => $incomingCall->From,
                ]);

                $response = $this->post(route('incoming-call'), $incomingCall->toArray());
                $call     = Call::where('external_id', $incomingCall->CallSid)->first();
                
                $this->assertNotNull($call);
               
                $this->assertDatabaseHas('calls', [
                    'id'                            => $call->id,
                    'external_id'                   => $incomingCall->CallSid,
                    'phone_number_id'               => $phoneNumber->id,
                    'status'                        => 'Ringing',
                    'forwarded_to'                  => $config->forwardToPhoneNumber(),
                    'keyword_tracking_pool_id'      => $pool->id,
                    'keyword_tracking_pool_name'    => $pool->name,
                    'keyword_tracking_pool_session_id' => $session->id,
                    'source'                        => $session->getSource($company->source_param, $company->source_referrer_when_empty),
                    'medium'                        => $session->getMedium($company->medium_param),
                    'content'                       => $session->getContent($company->content_param),
                    'campaign'                      => $session->getCampaign($company->campaign_param),
                    'keyword'                       => $session->getKeyword($company->keyword_param),
                    'is_paid'                       => $session->getIsPaid($company->medium_param),
                    'is_organic'                    => $session->getIsOrganic($company->medium_param),
                    'is_direct'                     => $session->getIsDirect(),
                    'is_referral'                   => $session->getIsReferral(),
                ]);
                
                Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
                    return $company->id === $event->call->company_id && $event->name === Webhook::ACTION_CALL_START;
                });
                
                //  Update the call
                $incomingCall->CallStatus = 'Answered';
                $response = $this->post(route('incoming-call-status-changed'), $incomingCall->toArray());
                $this->assertDatabaseHas('calls', [
                    'id'                            => $call->id,
                    'external_id'                   => $incomingCall->CallSid,
                    'phone_number_id'               => $phoneNumber->id,
                    'status'                        => 'Answered',
                    'forwarded_to'                  => $config->forwardToPhoneNumber(),
                    'keyword_tracking_pool_id'      => $pool->id,
                    'keyword_tracking_pool_name'    => $pool->name,
                    'keyword_tracking_pool_session_id' => $session->id,
                    'source'                        => $session->getSource($company->source_param, $company->source_referrer_when_empty),
                    'medium'                        => $session->getMedium($company->medium_param),
                    'content'                       => $session->getContent($company->content_param),
                    'campaign'                      => $session->getCampaign($company->campaign_param),
                    'keyword'                       => $session->getKeyword($company->keyword_param),
                    'is_paid'                       => $session->getIsPaid($company->medium_param),
                    'is_organic'                    => $session->getIsOrganic($company->medium_param),
                    'is_direct'                     => $session->getIsDirect(),
                    'is_referral'                   => $session->getIsReferral(),
                ]);

                //  End the call
                $incomingCall->CallStatus   = 'Completed';
                $incomingCall->CallDuration = mt_rand(99,999); 
                $response = $this->post(route('incoming-call-status-changed'), $incomingCall->toArray());
                $this->assertDatabaseHas('calls', [
                    'id'                            => $call->id,
                    'external_id'                   => $incomingCall->CallSid,
                    'phone_number_id'               => $phoneNumber->id,
                    'status'                        => 'Completed',
                    'forwarded_to'                  => $config->forwardToPhoneNumber(),
                    'keyword_tracking_pool_id'      => $pool->id,
                    'keyword_tracking_pool_name'    => $pool->name,
                    'keyword_tracking_pool_session_id' => $session->id,
                    'source'                        => $session->getSource($company->source_param, $company->source_referrer_when_empty),
                    'medium'                        => $session->getMedium($company->medium_param),
                    'content'                       => $session->getContent($company->content_param),
                    'campaign'                      => $session->getCampaign($company->campaign_param),
                    'keyword'                       => $session->getKeyword($company->keyword_param),
                    'is_paid'                       => $session->getIsPaid($company->medium_param),
                    'is_organic'                    => $session->getIsOrganic($company->medium_param),
                    'is_direct'                     => $session->getIsDirect(),
                    'is_referral'                   => $session->getIsReferral(),
                ]);

                Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
                    return $company->id === $event->call->company_id && $event->name === Webhook::ACTION_CALL_END;
                });
                
                $url                = $this->faker()->url;
                $duration           = $incomingCall->CallDuration;
                $recordingSid       = str_random(40);
                $recordingContent   = random_bytes(9999);
                $recordingPath      = 'accounts/' . $call->account_id . '/companies/' . $call->company_id . '/recordings/Call-' . $call->id . '.mp3';
                $transcriptionPath  = 'accounts/' . $call->account_id . '/companies/' . $call->company_id . '/transcriptions/Transcription-' . $call->id . '.json';
                
                $this->mock('HTTPClient', function($mock) use($url, $recordingContent){
                    $mock->shouldReceive('request')
                        ->with('GET', $url . '.mp3')
                        ->andReturn($mock);
        
                    $mock->shouldReceive('getBody')
                        ->andReturn($recordingContent);
                });
        
                $this->mock(TwilioClient::class, function($mock) use($recordingSid){
                    $mock->shouldReceive('recordings')
                        ->with($recordingSid)
                        ->andReturn($mock)
                        ->once();
                    
                    $mock->shouldReceive('delete')
                        ->once();
                });

                $this->mock(TranscribeService::class, function($mock) use($recordingContent){
                    $jobId   = str_random(10);
                    $fileUrl = $this->faker()->url;
                    $mock->shouldReceive('startTranscription')
                        ->andReturn($jobId)
                        ->once();
                        
                    $mock->shouldReceive('waitForUrl')
                        ->with($jobId)
                        ->andReturn($fileUrl)
                        ->once();

                    $mock->shouldReceive('downloadFromUrl')
                        ->with($fileUrl)
                        ->andReturn($recordingContent)
                        ->once();

                    $mock->shouldReceive('transformContent')
                        ->with($recordingContent)
                        ->andReturn([
                            'hello_world'
                        ])
                        ->once();

                    $mock->shouldReceive('deleteTranscription')
                        ->with($jobId)
                        ->once();
                });

                $response = $this->post(route('incoming-call-recording-available', [
                    'AccountSid'        => config('services.twilio.sid'),
                    'CallSid'           => $call->external_id,
                    'RecordingSid'      => $recordingSid,
                    'RecordingUrl'      => $url,
                    'RecordingDuration' => $duration
                ]));

                Storage::assertExists($recordingPath);
                Storage::assertExists($transcriptionPath);
                
                $this->assertDatabaseHas('call_recordings', [
                    'call_id'               => $call->id,
                    'external_id'           => $recordingSid,
                    'path'                  => $recordingPath,
                    'transcription_path'    => $transcriptionPath,
                    'duration'              => $duration
                ]);

                //  Test ending the session
                $this->assertDatabaseHas('keyword_tracking_pool_sessions', [
                    'id'       => $session->id,
                    'ended_at' => null
                ]);
            }
        }
    }
}
