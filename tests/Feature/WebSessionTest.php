<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use App\Models\Plugin;
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
use App\Services\SessionService;
use Queue;
use Storage;
use App;

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
            'exclusion_rules' => [],
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

        $domainName = $this->faker()->domainName;
        $response = $this->withHeaders([
                        'Origin' => $domainName
                    ])
                    ->post(route('web-start-session', [
                        'device_width'  => $browser->device_width,
                        'device_height' => $browser->device_height,
                        'user_agent'    => $browser->user_agent,
                        'landing_url'   => $browser->landing_url,
                        'http_referrer' => $browser->http_referrer,
                        'company_id'    => $company->id
                    ]));

        $response->assertJSON([
            'session'  => null,
            'swapping' => null,
            'phone'    => null
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', $domainName);
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
            'exclusion_rules' => [],
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
            'swap_rules' => $failedRules,
            'last_assigned_at' => null,
        ]);

        // Detached numbers
        $phoneNumber = factory(PhoneNumber::class, 10)->create([
            'category'     => 'ONLINE',
            'sub_category' => 'WEBSITE',
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by' => $this->user->id,
            'last_assigned_at' => null
        ])->last();
        $browser = factory(Browser::class)->make([
            'user_agent' => $this->faker()->firefox // Request as firefox so it fails against chrome match
        ]);
        $domainName = $this->faker()->domainName;
        $response   = $this->withHeaders(['Origin' => $domainName])->post(route('web-start-session', [
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
        $response->assertHeader('Access-Control-Allow-Origin', $domainName);

        $this->assertDatabaseMissing('phone_numbers', [
            'id'                => $phoneNumber->id,
            'last_assigned_at'  => null
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
            'created_by' => $this->user->id,
            'last_assigned_at'=> null
        ]);

        $sessionService = App::make(SessionService::class);
        
        //  Test 3 rounds of number rotations
        for( $i = 0; $i < 3; $i++){
            $poolNumbers->each(function($phoneNumber) use($sessionService, $pool, $company, $i){
                $browser = factory(Browser::class)->make();
                $domainName = $this->faker()->domainName;
                $response = $this->withHeaders(['Origin'=>$domainName])->post(route('web-start-session', [
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
                $response->assertHeader('Access-Control-Allow-Origin', $domainName);

                $this->assertDatabaseMissing('phone_numbers', [
                    'id' => $phoneNumber->id,
                    'last_assigned_at' => null
                ]);

                $this->assertDatabaseHas('keyword_tracking_pool_sessions', [
                    'keyword_tracking_pool_id'  => $pool->id,
                    'phone_number_id'           => $phoneNumber->id,
                    'device_width'              => $browser->device_width,
                    'device_height'             => $browser->device_height,
                    'landing_url'               => $browser->landing_url,
                    'http_referrer'             => $browser->http_referrer,
                    'source'                    => $sessionService->getSource($company->source_param, $browser->http_referrer, $browser->landing_url, $company->source_referrer_when_empty),
                    'medium'                    => $sessionService->getMedium($company->medium_param, $browser->landing_url),
                    'content'                   => $sessionService->getContent($company->content_param, $browser->landing_url),
                    'campaign'                  => $sessionService->getCampaign($company->campaign_param, $browser->landing_url),
                    'keyword'                   => $sessionService->getKeyword($company->keyword_param, $browser->landing_url),
                    'is_organic'                => $sessionService->getIsOrganic($company->medium_param, $browser->http_referrer, $browser->landing_url),
                    'is_paid'                   => $sessionService->getIsPaid($company->medium_param, $browser->landing_url),
                    'is_direct'                 => $sessionService->getIsDirect($browser->http_referrer),
                    'is_referral'               => $sessionService->getIsReferral($browser->http_referrer),
                    'is_search'                 => $sessionService->getIsSearch($browser->http_referrer),
                ]);
            });
        }
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
        $domainName = $this->faker()->domainName;
        $response = $this->withHeaders(['Origin' => $domainName])->post(route('web-collect'), [
            'url' => $url,
            'session_uuid' => $response['session']['uuid'],
            'session_token'=> $response['session']['token']
        ]);
        $response->assertJSON([
            'status' => 'OK'
        ]);
        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', $domainName);
        $this->assertDatabaseHas('keyword_tracking_pool_sessions', [
            'uuid'     => $sessionUUID,
            'last_url' => $url
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
            'transcription_enabled' => 1,
            'keypress_enabled'      => 0
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
                'id' => $phoneNumber->id,
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
                return $company->id === $event->call->company_id && $event->name === Plugin::EVENT_CALL_START;
            });

            //  Update the call
            $incomingCall->DialCallStatus = 'Completed';
            $response = $this->post(route('incoming-call-completed'), $incomingCall->toArray());
            $this->assertDatabaseHas('calls', [
                'id'                            => $call->id,
                'external_id'                   => $incomingCall->CallSid,
                'phone_number_id'               => $phoneNumber->id,
                'status'                        => $incomingCall->DialCallStatus,
                'forwarded_to'                  => $config->forwardToPhoneNumber(),
                'keyword_tracking_pool_id'      => null,
                'keyword_tracking_pool_name'    => null,
                'source'                        => $phoneNumber->source,
                'content'                       => $phoneNumber->content,
                'campaign'                      => $phoneNumber->campaign,
                'medium'                        => $phoneNumber->medium,
            ]);

            //  Update the call duration
            $incomingCall->CallDuration = mt_rand(99,999); 
            $response = $this->post(route('incoming-call-duration'), $incomingCall->toArray());
            $this->assertDatabaseHas('calls', [
                'duration'        => $incomingCall->CallDuration,
                'forwarded_to'    => $config->forwardToPhoneNumber()
            ]);

            Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
                return $company->id === $event->call->company_id && $event->name === Plugin::EVENT_CALL_END;
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
            'keypress_enabled'      => 0,
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
                    'utm_medium'    => 'cpc',
                    'utm_term'      => str_random(40),
                ];
                $browser  = factory(Browser::class)->make([
                    'http_referrer' => 'https://google.com/',
                    'landing_url'   => $this->faker()->url . '?' . http_build_query($params) 
                ]);
                $response = $this->withHeaders([
                    'User-Agent' => $browser->user_agent
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
                    'id' => $phoneNumber->id
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
                    'source'                   => $params['utm_source'],
                    'medium'                   => $params['utm_medium'],
                    'content'                  => $params['utm_content'],
                    'campaign'                 => $params['utm_campaign'],
                    'keyword'                  => $params['utm_term'],
                    'is_paid'                  => 1,
                    'is_search'                => 1,
                    'is_organic'               => 0,
                    'is_direct'                => 0,
                    'is_referral'              => 0,
                    'ended_at'                 => null
                ]);

                $lastURL = $this->faker()->url;
                $response = $this->post(route('web-collect', [
                    'url'           => $lastURL,
                    'session_uuid'  => $sessionUUID,
                    'session_token' => $sessionToken,
                ]));
                $response->assertJSON([
                    'status' => 'OK'
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
                    'ended_at'                 => null,
                    'source'                   => $params['utm_source'],
                    'medium'                   => $params['utm_medium'],
                    'content'                  => $params['utm_content'],
                    'campaign'                 => $params['utm_campaign'],
                    'keyword'                  => $params['utm_term'],
                    'is_paid'                  => 1,
                    'is_search'                => 1,
                    'is_organic'               => 0,
                    'is_direct'                => 0,
                    'is_referral'              => 0,
                    'active'                   => 1
                ]);
                
                $session = KeywordTrackingPoolSession::where('uuid', $sessionUUID)->first();
                //
                //  Keep alive
                //
                $response = $this->post(route('web-keep-alive', [
                    'session_uuid'  => $sessionUUID,
                    'session_token' => $sessionToken,
                ]));
                $response->assertJSON([
                    'status' => 'OK'
                ]);
                $response->assertStatus(200);

                //
                //  Make call
                //
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
                    'source'                        => $session->source,
                    'medium'                        => $session->medium,
                    'content'                       => $session->content,
                    'campaign'                      => $session->campaign,
                    'keyword'                       => $session->keyword,
                    'is_paid'                       => $session->is_paid,
                    'is_organic'                    => $session->is_organic,
                    'is_direct'                     => $session->is_direct,
                    'is_referral'                   => $session->is_referral,
                ]);
                
                Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
                    return $company->id === $event->call->company_id && $event->name === Plugin::EVENT_CALL_START;
                });
                
                //  Update the call
                $incomingCall->DialCallStatus = 'Answered';
                $response = $this->post(route('incoming-call-completed'), $incomingCall->toArray());
                $this->assertDatabaseHas('calls', [
                    'id'                            => $call->id,
                    'external_id'                   => $incomingCall->CallSid,
                    'phone_number_id'               => $phoneNumber->id,
                    'status'                        => $incomingCall->DialCallStatus,
                    'forwarded_to'                  => $config->forwardToPhoneNumber(),
                    'keyword_tracking_pool_id'      => $pool->id,
                    'keyword_tracking_pool_name'    => $pool->name,
                    'keyword_tracking_pool_session_id' => $session->id,
                    'source'                        => $session->source,
                    'medium'                        => $session->medium,
                    'content'                       => $session->content,
                    'campaign'                      => $session->campaign,
                    'keyword'                       => $session->keyword,
                    'is_paid'                       => $session->is_paid,
                    'is_organic'                    => $session->is_organic,
                    'is_direct'                     => $session->is_direct,
                    'is_referral'                   => $session->is_referral,
                ]);

                //  End the call
                $incomingCall->CallDuration = mt_rand(99,999); 
                $response = $this->post(route('incoming-call-duration'), $incomingCall->toArray());
                $this->assertDatabaseHas('calls', [
                    'id'                            => $call->id,
                    'external_id'                   => $incomingCall->CallSid,
                    'phone_number_id'               => $phoneNumber->id,
                    'forwarded_to'                  => $config->forwardToPhoneNumber(),
                    'keyword_tracking_pool_id'      => $pool->id,
                    'keyword_tracking_pool_name'    => $pool->name,
                    'keyword_tracking_pool_session_id' => $session->id,
                    'source'                        => $session->source,
                    'medium'                        => $session->medium,
                    'source'                        => $session->source,
                    'medium'                        => $session->medium,
                    'content'                       => $session->content,
                    'campaign'                      => $session->campaign,
                    'keyword'                       => $session->keyword,
                    'is_paid'                       => $session->is_paid,
                    'is_organic'                    => $session->is_organic,
                    'is_direct'                     => $session->is_direct,
                    'is_referral'                   => $session->is_referral,
                    'is_search'                     => $session->is_search,
                    'duration'                      => $incomingCall->CallDuration 
                ]);

                Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
                    return $company->id === $event->call->company_id && $event->name === Plugin::EVENT_CALL_END;
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

                //  Test pausing the session
                $this->assertDatabaseHas('keyword_tracking_pool_sessions', [
                    'id'       => $session->id,
                    'ended_at' => null
                ]);
                
                $response = $this->post(route('web-pause-session'),[ 
                    'session_uuid'  => $sessionUUID,
                    'session_token' => $sessionToken,
                ]);
                $response->assertStatus(200);
                
                $this->assertDatabaseHas('keyword_tracking_pool_sessions', [
                    'id'       => $session->id,
                    'active'   => 0,
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
                    'utm_term'      => str_random(40),
                ];
                $browser  = factory(Browser::class)->make([
                    'http_referrer' => 'https://yahoo.com',
                    'landing_url'   => $this->faker()->url . '?' . http_build_query($params) 
                ]); 

                $response = $this->withHeaders([
                    'User-Agent' => $browser->user_agent
                ])->post(route('web-start-session', [
                    'guuid'         => $gUUID,
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

                //
                //  Make sure the old session was ended
                //
                $this->assertDatabaseMissing('keyword_tracking_pool_sessions', [
                    'id' => $session->id,
                    'ended_at' => null
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

                $this->assertDatabaseMissing('phone_numbers', [
                    'id' => $phoneNumber->id,
                    'last_assigned_at' => null
                ]);

                $lastURL = $this->faker()->url;
                $response = $this->post(route('web-collect', [
                    'session_uuid'  => $sessionUUID,
                    'session_token' => $sessionToken,
                    'url'           => $lastURL
                ]));
                $response->assertJSON([
                    'status' => 'OK'
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
                    'source'                   => $params['utm_source'],
                    'medium'                   => $params['utm_medium'],
                    'content'                  => $params['utm_content'],
                    'campaign'                 => $params['utm_campaign'],
                    'keyword'                  => $params['utm_term'],
                    'is_paid'                  => 0,
                    'is_search'                => 1,
                    'is_organic'               => 1,
                    'is_direct'                => 0,
                    'is_referral'              => 0,
                    'active'                   => 1,
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
                    'source'                        => $params['utm_source'],
                    'medium'                        => $params['utm_medium'],
                    'content'                       => $params['utm_content'],
                    'campaign'                      => $params['utm_campaign'],
                    'keyword'                       => $params['utm_term'],
                    'is_paid'                       => 0,
                    'is_search'                     => 1,
                    'is_organic'                    => 1,
                    'is_direct'                     => 0,
                    'is_referral'                   => 0,
                ]);
                
                Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
                    return $company->id === $event->call->company_id && $event->name === Plugin::EVENT_CALL_START;
                });
                
                //  Update the call
                $incomingCall->DialCallStatus = 'Answered';
                $response = $this->post(route('incoming-call-completed'), $incomingCall->toArray());
                $this->assertDatabaseHas('calls', [
                    'id'                            => $call->id,
                    'external_id'                   => $incomingCall->CallSid,
                    'phone_number_id'               => $phoneNumber->id,
                    'status'                        => $incomingCall->DialCallStatus,
                    'forwarded_to'                  => $config->forwardToPhoneNumber(),
                    'keyword_tracking_pool_id'      => $pool->id,
                    'keyword_tracking_pool_name'    => $pool->name,
                    'keyword_tracking_pool_session_id' => $session->id,
                    'source'                        => $params['utm_source'],
                    'medium'                        => $params['utm_medium'],
                    'content'                       => $params['utm_content'],
                    'campaign'                      => $params['utm_campaign'],
                    'keyword'                       => $params['utm_term'],
                    'is_paid'                       => 0,
                    'is_search'                     => 1,
                    'is_organic'                    => 1,
                    'is_direct'                     => 0,
                    'is_referral'                   => 0,
                    'duration'                      => null
                ]);

                //  End the call
                $incomingCall->CallDuration = mt_rand(99,999); 
                $response = $this->post(route('incoming-call-duration'), $incomingCall->toArray());
                $this->assertDatabaseHas('calls', [
                    'id'                            => $call->id,
                    'external_id'                   => $incomingCall->CallSid,
                    'phone_number_id'               => $phoneNumber->id,
                    'forwarded_to'                  => $config->forwardToPhoneNumber(),
                    'keyword_tracking_pool_id'      => $pool->id,
                    'keyword_tracking_pool_name'    => $pool->name,
                    'keyword_tracking_pool_session_id' => $session->id,
                    'source'                        => $session->source,
                    'medium'                        => $session->medium,
                    'content'                       => $session->content,
                    'campaign'                      => $session->campaign,
                    'keyword'                       => $session->keyword,
                    'is_paid'                       => $session->is_paid,
                    'is_search'                     => $session->is_search,
                    'is_organic'                    => $session->is_organic,
                    'is_direct'                     => $session->is_direct,
                    'is_referral'                   => $session->is_referral,
                    'duration'                      => $incomingCall->CallDuration
                ]);

                Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
                    return $company->id === $event->call->company_id && $event->name === Plugin::EVENT_CALL_END;
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
