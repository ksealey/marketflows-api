<?php

namespace Tests\Feature\Events;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class SessionTest extends TestCase
{
    use \Tests\CreatesUser, RefreshDatabase;

    /**
     *  Test starting a session where a number should be assigned
     * 
     * @group feature-events-sessions
     */
    public function testStartSession()
    {
        $user = $this->createUser();

        $target1 = mt_rand(1111111111, 9999999999);
        $target2 = '813#######';

        $pool = $this->createPhoneNumberPool([
            'company_id'  => $this->company->id,
            'created_by'  => $user->id,
            'category'    => 'ONLINE',
            'sub_category'=> 'WEBSITE_SESSION',
            'swap_rules'  => [
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'type' => 'ALL'
                            ]
                            
                        ]
                    ]
                ],
                'targets' => [
                    $target1,
                    $target2,
                ]

            ]
        ]);

        $phone1 = $this->createPhoneNumber([
            'company_id'  => $this->company->id,
            'created_by'  => $user->id,
            'phone_number_pool_id' => $pool->id
        ]);

        $phone2 = $this->createPhoneNumber([
            'company_id'  => $this->company->id,
            'created_by'  => $user->id,
            'phone_number_pool_id' => $pool->id
        ]);

        $phone3 = $this->createPhoneNumber([
            'company_id'  => $this->company->id,
            'created_by'  => $user->id,
            'phone_number_pool_id' => $pool->id
        ]);

        $persistedId = Str::uuid();
        $response = $this->json('POST', route('events-session-start'), [
            'persisted_id'  => $persistedId,
            'company_id'    => $this->company->id,
            'device_width'  => '10',
            'device_height' => '40',
            'entry_url'     => 'http://mysite.com/lp?utm_source=Facebook&utm_content=ABC'

        ], $this->authHeaders());

        $response->assertJSON([
            'number' => [
                'id'           => $phone1->id,
                'country_code' => $phone1->country_code,
                'number'       => $phone1->number
            ],
            'targets' => [
                $target1,
                $target2,
            ],
            'session' => [
                'persisted_id' => $persistedId
            ]
        ]);

        //  Make sure session with events were stored
        $sessionId1 = json_decode($response->getContent())->session->id;

        $this->assertDatabaseHas('sessions', [
            'id'           => $sessionId1,
            'persisted_id' => $persistedId,
            'ended_at'     => null
        ]);

        $this->assertDatabaseHas('session_events', [
            'session_id'  => $sessionId1,
             'event_type' => 'StartSession'
        ]);

        //
        //  Make sure that another request with the same persisted_id returns the same phone
        //
        $response = $this->json('POST', route('events-session-start'), [
            'persisted_id'  => $persistedId,
            'company_id'    => $this->company->id,
            'device_width'  => '10',
            'device_height' => '40',
            'entry_url'     => 'http://mysite.com/lp?utm_source=Facebook&utm_content=ABC'

        ], $this->authHeaders());

        $response->assertJSON([
            'number' => [
                'id'           => $phone1->id,
                'country_code' => $phone1->country_code,
                'number'       => $phone1->number
            ],
            'targets' => [
                $target1,
                $target2,
            ],
            'session' => [
                'persisted_id' => $persistedId
            ]
        ]);

        //  Make sure session with events were stored
        $sessionId2 = json_decode($response->getContent())->session->id;
        $this->assertDatabaseHas('sessions', [
            'id'           => $sessionId2,
            'persisted_id' => $persistedId,
            'ended_at'     => null
        ]);
        $this->assertDatabaseHas('session_events', [
            'session_id'  => $sessionId2,
            'event_type' => 'StartSession'
        ]);

        //  Make sure the first session was ended
        $this->assertDatabaseHas('session_events', [
            'session_id'  => $sessionId1,
             'event_type' => 'EndSession'
        ]);
    }

    /**
     *  Test starting a session rotates numbers properly
     * 
     * @group feature-events-sessions
     */
    public function testSessionsRotateNumbers()
    {
        $user = $this->createUser();

        $target1 = mt_rand(1111111111, 9999999999);
        $target2 = '813#######';

        $pool = $this->createPhoneNumberPool([
            'company_id'  => $this->company->id,
            'created_by'  => $user->id,
            'category'    => 'ONLINE',
            'sub_category'=> 'WEBSITE_SESSION',
            'swap_rules'  => [
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'type' => 'ALL'
                            ]
                            
                        ]
                    ]
                ],
                'targets' => [
                    $target1,
                    $target2,
                ]

            ]
        ]);

        $phoneNumbers = [];
        for($i = 0; $i < 3; $i++){
            $phoneNumbers[] = $this->createPhoneNumber([
                'company_id'  => $this->company->id,
                'created_by'  => $user->id,
                'phone_number_pool_id' => $pool->id
            ]);
        }
        
        //  Do 5 rounds of 3 numbers
        for($i = 0; $i < 5; $i++){
            foreach($phoneNumbers as $phoneNumber){
                // Test phone 1 is served
                $response = $this->json('POST', route('events-session-start'), [
                    'company_id'    => $this->company->id,
                    'device_width'  => '10',
                    'device_height' => '40',
                    'entry_url'     => 'http://mysite.com/lp?utm_source=Facebook&utm_content=ABC'

                ], $this->authHeaders());

                $response->assertJSON([
                    'number' => [
                        'id'           => $phoneNumber->id,
                        'country_code' => $phoneNumber->country_code,
                        'number'       => $phoneNumber->number
                    ],
                    'targets' => [
                        $target1,
                        $target2,
                    ]
                ]);

                $sessionId = json_decode($response->getContent())->session->id;
                $this->assertDatabaseHas('session_events', [
                    'session_id'  => $sessionId,
                    'event_type' => 'StartSession'
                ]);
        
            }
        }
    }

    /**
     *  Test ending a session
     * 
     * @group feature-events-sessions
     */
    public function testEndSession()
    {
        $user = $this->createUser();

        $target1 = mt_rand(1111111111, 9999999999);
        $target2 = '813#######';

        $pool = $this->createPhoneNumberPool([
            'company_id'  => $this->company->id,
            'created_by'  => $user->id,
            'category'    => 'ONLINE',
            'sub_category'=> 'WEBSITE_SESSION',
            'swap_rules'  => [
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'type' => 'ALL'
                            ]
                            
                        ]
                    ]
                ],
                'targets' => [
                    $target1,
                    $target2,
                ]

            ]
        ]);

        $phoneNumbers = [];
        for($i = 0; $i < 3; $i++){
            $phoneNumbers[] = $this->createPhoneNumber([
                'company_id'  => $this->company->id,
                'created_by'  => $user->id,
                'phone_number_pool_id' => $pool->id
            ]);
        }
        
        //  Create a session
        $response = $this->json('POST', route('events-session-start'), [
            'company_id'    => $this->company->id,
            'device_width'  => '10',
            'device_height' => '40',
            'entry_url'     => 'http://mysite.com/lp?utm_source=Facebook&utm_content=ABC'
        ], $this->authHeaders());

        $response->assertJSON([
            'number' => [
                'id'           => $phoneNumbers[0]->id,
                'country_code' => $phoneNumbers[0]->country_code,
                'number'       => $phoneNumbers[0]->number
            ],
            'targets' => [
                $target1,
                $target2,
            ]
        ]);

        $sessionData = json_decode($response->getContent())->session;
        $sessionId   = $sessionData->id;
        $sessionToken= $sessionData->token;
        
        //  Then end it
        $response = $this->json('POST', route('events-session-end'), [
            'session_id'    => $sessionId,
            'session_token' => $sessionToken     
        ], $this->authHeaders());

        $response->assertJSON([
            'message' => 'Session Ended'
        ]);

        $this->assertDatabaseHas('session_events', [
            'session_id'  => $sessionId,
            'event_type' => 'EndSession'
        ]);
    }
}
