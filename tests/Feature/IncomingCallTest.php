<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\AudioClip;
use \App\Models\PhoneNumber;
use \App\Models\PhoneNumberPool;
use \App\Models\Campaign;
use \App\Models\CampaignPhoneNumber;
use \App\Models\CampaignPhoneNumberPool;
use \App\External\TwilioCall;
use \App\Events\IncomingCallEvent;
use \Event;

class InboundCallTest extends TestCase
{
    use \Tests\CreatesUser;
    
    /**
     * Test handling a valid inbound call 
     * 
     * @group incoming-call
     */
    public function testHandleValidInboundCallForward()
    {
        //  Create phone numbers, campaigns, etc
        $user        = $this->createUser();
        
        $campaign    = factory(Campaign::class)->create([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => date('Y-m-d H:i:s', strtotime('now -10 days'))
        ]);

        $phoneNumber = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        CampaignPhoneNumber::create([
            'campaign_id'     => $campaign->id,
            'phone_number_id' => $phoneNumber->id
        ]);

    
        $twilioCall = factory(TwilioCall::class)->make([
            'Called' => $phoneNumber->phoneNumber() // Number called
        ]);

        Event::fake();

        $response = $this->json('GET', 'http://localhost/v1/incoming/call', [
            'CallSid'    => $twilioCall->CallSid,
            'CallStatus' => $twilioCall->CallStatus,
            'Called'     => $twilioCall->Called,
            'Caller'     => $twilioCall->Caller
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee('<Response><Dial answerOnBridge="true" record="false"><Number>' . $phoneNumber->forwardToPhoneNumber() . '</Number></Dial></Response>');

        Event::assertDispatched(IncomingCallEvent::class);
    }

    /**
     * Test handling a valid inbound call with a recording
     * 
     * @group incoming-call
     */
    public function testHandleValidInboundCallForwardWithRecording()
    {
        //  Create phone numbers, campaigns, etc
        $user        = $this->createUser();
        
        $campaign    = factory(Campaign::class)->create([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => date('Y-m-d H:i:s', strtotime('now -10 days'))
        ]);

        $phoneNumber = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'recording_enabled_at' => date('Y-m-d H:i:s')
        ]);

        CampaignPhoneNumber::create([
            'campaign_id'     => $campaign->id,
            'phone_number_id' => $phoneNumber->id
        ]);
    
        $twilioCall = factory(TwilioCall::class)->make([
            'Called' => $phoneNumber->phoneNumber() // Number called
        ]);

        Event::fake();

        $response = $this->json('GET', 'http://localhost/v1/incoming/call', [
            'CallSid' => $twilioCall->CallSid,
            'CallStatus' => $twilioCall->CallStatus,
            'Called'  => $twilioCall->Called,
            'Caller'  => $twilioCall->Caller
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        $expectedTwiml = '<Response><Dial answerOnBridge="true" record="true"><Number>' . $phoneNumber->forwardToPhoneNumber() . '</Number></Dial></Response>';
        $response->assertSee($expectedTwiml);

        Event::assertDispatched(IncomingCallEvent::class);

    }

    /**
     * Test handling a valid inbound call with a recording and audio clip
     * 
     * @group incoming-call
     */
    public function testHandleValidInboundCallForwardWithRecordingAndAudio()
    {
        //  Create phone numbers, campaigns, etc
        $user        = $this->createUser();
        
        $campaign    = factory(Campaign::class)->create([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => date('Y-m-d H:i:s', strtotime('now -10 days'))
        ]);

        $audioClip = factory(AudioClip::class)->create([
            'company_id'  => $user->company_id,
            'created_by'  => $user->id
        ]);

        $phoneNumber = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'recording_enabled_at' => date('Y-m-d H:i:s'),
            'audio_clip_id'        => $audioClip->id
        ]);

        CampaignPhoneNumber::create([
            'campaign_id'     => $campaign->id,
            'phone_number_id' => $phoneNumber->id
        ]);
    
        $twilioCall = factory(TwilioCall::class)->make([
            'Called' => $phoneNumber->phoneNumber() // Number called
        ]);

        Event::fake();

        $response = $this->json('GET', 'http://localhost/v1/incoming/call', [
            'CallSid' => $twilioCall->CallSid,
            'CallStatus' => $twilioCall->CallStatus,
            'Called'  => $twilioCall->Called,
            'Caller' => $twilioCall->Caller
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        $expectedTwiml = '<Response><Play>' . $audioClip->getURL() . '</Play><Dial answerOnBridge="true" record="true"><Number>' . $phoneNumber->forwardToPhoneNumber() . '</Number></Dial></Response>';
        $response->assertSee($expectedTwiml);
        
        Event::assertDispatched(IncomingCallEvent::class);
    }

    /**
     * Test handling a valid inbound call with a recording and audio clip and whisper
     * 
     * @group incoming-call
     */
    public function testHandleValidInboundCallForwardWithRecordingAndAudioAndWhisper()
    {
        //  Create phone numbers, campaigns, etc
        $user        = $this->createUser();
        
        $campaign    = factory(Campaign::class)->create([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => date('Y-m-d H:i:s', strtotime('now -10 days'))
        ]);

        $audioClip = factory(AudioClip::class)->create([
            'company_id'  => $user->company_id,
            'created_by'  => $user->id
        ]);

        $whisperOptions = [
            'whisper_message'      => 'Hello there!',
            'whisper_language'     => 'en',
            'whisper_voice'        => 'alice'
        ];

        $phoneNumber = factory(PhoneNumber::class)->create(array_merge([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'recording_enabled_at' => date('Y-m-d H:i:s'),
            'audio_clip_id'        => $audioClip->id,
           
        ], $whisperOptions));

        CampaignPhoneNumber::create([
            'campaign_id'     => $campaign->id,
            'phone_number_id' => $phoneNumber->id
        ]);
    
        $twilioCall = factory(TwilioCall::class)->make([
            'Called' => $phoneNumber->phoneNumber() // Number called
        ]);

        Event::fake();

        $response = $this->json('GET', 'http://localhost/v1/incoming/call', [
            'CallSid' => $twilioCall->CallSid,
            'CallStatus' => $twilioCall->CallStatus,
            'Called'  => $twilioCall->Called,
            'Caller' => $twilioCall->Caller
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        $expectedTwiml = '<Response><Play>' . $audioClip->getURL() . '</Play><Dial answerOnBridge="true" record="true"><Number url="' . str_replace('&', '&amp;', route('whisper', $whisperOptions)) . '">' . $phoneNumber->forwardToPhoneNumber() . '</Number></Dial></Response>';
        $response->assertSee($expectedTwiml);
        
        Event::assertDispatched(IncomingCallEvent::class);
    }


    /**
     * Test handling a valid inbound call tied to Pool
     * 
     * @group incoming-call
     */
    public function testHandleValidInboundCallForwardWithPool()
    {
        //  Create phone numbers, campaigns, etc
        $user        = $this->createUser();
        
        $campaign    = factory(Campaign::class)->create([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => date('Y-m-d H:i:s', strtotime('now -10 days'))
        ]);

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id
        ]);

        $phoneNumber = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'phone_number_pool_id' => $pool->id
        ]);

        CampaignPhoneNumberPool::create([
            'campaign_id'          => $campaign->id,
            'phone_number_pool_id' => $pool->id
        ]);

    
        $twilioCall = factory(TwilioCall::class)->make([
            'Called' => $phoneNumber->phoneNumber() // Number called
        ]);

        Event::fake();

        $response = $this->json('GET', 'http://localhost/v1/incoming/call', [
            'CallSid' => $twilioCall->CallSid,
            'CallStatus' => $twilioCall->CallStatus,
            'Called'  => $twilioCall->Called,
            'Caller' => $twilioCall->Caller
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee('<Response><Dial answerOnBridge="true" record="false"><Number>' . $pool->forwardToPhoneNumber() . '</Number></Dial></Response>');

        Event::assertDispatched(IncomingCallEvent::class);
    }

    /** 
     * Test handling a valid inbound pool call with a recording
     * 
     * @group incoming-call
    */
    public function testHandleValidInboundCallForwardWithRecordingWithPool()
    {
        //  Create phone numbers, campaigns, etc
        $user        = $this->createUser();
        
        $campaign    = factory(Campaign::class)->create([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => date('Y-m-d H:i:s', strtotime('now -10 days'))
        ]);

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'recording_enabled_at' => date('Y-m-d H:i:s')
        ]);

        $phoneNumber = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'phone_number_pool_id' => $pool->id
        ]);

        CampaignPhoneNumberPool::create([
            'campaign_id'          => $campaign->id,
            'phone_number_pool_id' => $pool->id
        ]);
    
        $twilioCall = factory(TwilioCall::class)->make([
            'Called' => $phoneNumber->phoneNumber() // Number called
        ]);

        Event::fake();

        $response = $this->json('GET', 'http://localhost/v1/incoming/call', [
            'CallSid' => $twilioCall->CallSid,
            'CallStatus' => $twilioCall->CallStatus,
            'Called'  => $twilioCall->Called,
            'Caller' => $twilioCall->Caller
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        $expectedTwiml = '<Dial answerOnBridge="true" record="true"><Number>' . $pool->forwardToPhoneNumber() . '</Number></Dial>';
        $response->assertSee($expectedTwiml);

        Event::assertDispatched(IncomingCallEvent::class);
    }

    /** 
     * Test handling a valid inbound call with a recording and audio clip
     * 
     * @group incoming-call
     */
    public function testHandleValidInboundCallForwardWithRecordingAndAudioWithPool()
    {
        //  Create phone numbers, campaigns, etc
        $user        = $this->createUser();
        
        $campaign    = factory(Campaign::class)->create([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => date('Y-m-d H:i:s', strtotime('now -10 days'))
        ]);

        $audioClip = factory(AudioClip::class)->create([
            'company_id'  => $user->company_id,
            'created_by'  => $user->id
        ]);

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'audio_clip_id' => $audioClip->id,
            'recording_enabled_at' => date('Y-m-d H:i:s')
        ]);

        $phoneNumber = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'phone_number_pool_id' => $pool->id
        ]);

        CampaignPhoneNumberPool::create([
            'campaign_id'          => $campaign->id,
            'phone_number_pool_id' => $pool->id
        ]);

        $twilioCall = factory(TwilioCall::class)->make([
            'Called' => $phoneNumber->phoneNumber() // Number called
        ]);

        Event::fake();

        $response = $this->json('GET', 'http://localhost/v1/incoming/call', [
            'CallSid' => $twilioCall->CallSid,
            'CallStatus' => $twilioCall->CallStatus,
            'Called'  => $twilioCall->Called,
            'Caller' => $twilioCall->Caller
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');

        $expectedTwiml = '<Response><Play>' . $audioClip->getURL() . '</Play><Dial answerOnBridge="true" record="true"><Number>' . $pool->forwardToPhoneNumber() . '</Number></Dial></Response>';
        $response->assertSee($expectedTwiml);

        Event::assertDispatched(IncomingCallEvent::class);
    }

    /** 
     * Test handling a valid inbound call with a recording and audio clip and whisper
     * 
     * @group incoming-call
     */
    public function testHandleValidInboundCallForwardWithRecordingAndAudioAndWhisperWithPool()
    {
        //  Create phone numbers, campaigns, etc
        $user        = $this->createUser();
        
        $campaign    = factory(Campaign::class)->create([
            'company_id'   => $user->company_id,
            'created_by'   => $user->id,
            'activated_at' => date('Y-m-d H:i:s', strtotime('now -10 days'))
        ]);

        $audioClip = factory(AudioClip::class)->create([
            'company_id'  => $user->company_id,
            'created_by'  => $user->id
        ]);

        $whisperOptions = [
            'whisper_message'      => 'Hello there!',
            'whisper_language'     => 'en',
            'whisper_voice'        => 'alice'
        ];

        $pool = factory(PhoneNumberPool::class)->create(array_merge([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'audio_clip_id' => $audioClip->id,
            'recording_enabled_at' => date('Y-m-d H:i:s')
        ], $whisperOptions));

        $phoneNumber = factory(PhoneNumber::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'phone_number_pool_id' => $pool->id
        ]);

        CampaignPhoneNumberPool::create([
            'campaign_id'          => $campaign->id,
            'phone_number_pool_id' => $pool->id
        ]);

        $twilioCall = factory(TwilioCall::class)->make([
            'Called' => $phoneNumber->phoneNumber() // Number called
        ]);

        Event::fake();

        $response = $this->json('GET', 'http://localhost/v1/incoming/call', [
            'CallSid' => $twilioCall->CallSid,
            'CallStatus' => $twilioCall->CallStatus,
            'Called'  => $twilioCall->Called,
            'Caller' => $twilioCall->Caller
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');

        $expectedTwiml = '<Response><Play>' . $audioClip->getURL() . '</Play><Dial answerOnBridge="true" record="true"><Number url="' . str_replace('&', '&amp;', route('whisper', $whisperOptions)) . '">' . $pool->forwardToPhoneNumber() . '</Number></Dial></Response>';
        $response->assertSee($expectedTwiml);

        Event::assertDispatched(IncomingCallEvent::class);
    }
}
