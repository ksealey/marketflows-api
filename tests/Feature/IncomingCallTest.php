<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use \App\Models\BlockedPhoneNumber;
use \App\Models\Company\Contact;
use \App\Models\Company\KeywordTrackingPool;
use \App\Models\Company\KeywordTrackingPoolSession;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\Call;
use \App\Models\Company\CallRecording;
use \App\Events\Company\CallEvent;
use \App\Models\Company\Webhook;
use App\Services\TranscribeService;
use Twilio\Rest\Client as TwilioClient;
use App\Jobs\ProcessCallRecordingJob;
use Storage;

class IncomingCallTest extends TestCase
{
    use \Tests\CreatesAccount, WithFaker;
    /**
     * Test handling an incoming call with no options
     * 
     * @group incoming-calls
     */
    public function testValidIncomingCallWithNoOptions()
    {
        Event::fake();

        $company     = $this->createCompany();
        $config      = $this->createConfig($company, [
            'greeting_enabled'       => 0,
            'greeting_message'       => null,
            'greeting_audio_clip_id' => null,

            'keypress_enabled'       => 0,
            'keypress_key'           => null,
            'keypress_message_type'  => null,
            'keypress_message'       => null,
            'keypress_audio_clip_id' => null,

            'whisper_enabled'        => 0,
            'whisper_message'        => null,

            'recording_enabled'      => 0,
            'transcription_enabled'  => 0
        ]);

        $phoneNumber = $this->createPhoneNumber($company, $config);
         
        $webhook = factory(Webhook::class)->create([
            'company_id' => $company->id,
            'action'     => Webhook::ACTION_CALL_START,
            'created_by' => $this->user->id
        ]);

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $phoneNumber->e164Format()
        ]);
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
        $response->assertHeader('Content-Type', 'application/xml');
        
        $response->assertStatus(200);

        $this->assertDatabaseHas('contacts', [
            'country_code' => PhoneNumber::countryCode($incomingCall->From),
            'number'       => PhoneNumber::number($incomingCall->From)
        ]);

        $this->assertDatabaseHas('calls', [
            'account_id'                    => $this->account->id,
            'company_id'                    => $company->id,
            'keyword_tracking_pool_id'      => null,
            'keyword_tracking_pool_name'    => null,
            'phone_number_id'               => $phoneNumber->id,
            'phone_number_name'             => $phoneNumber->name,
            'type'                          => $phoneNumber->type,
            'category'                      => $phoneNumber->category,
            'sub_category'                  => $phoneNumber->sub_category,
            'external_id'                   => $incomingCall->CallSid,
            'direction'                     => ucfirst($incomingCall->Direction),
            'status'                        => ucfirst($incomingCall->CallStatus),
            'source'                        => $phoneNumber->source,
            'medium'                        => $phoneNumber->medium,
            'content'                       => $phoneNumber->content,
            'campaign'                      => $phoneNumber->campaign,
            'keyword'                       => null,
            'is_paid'                       => null,
            'is_organic'                    => null, 
            'is_direct'                     => null,
            'is_referral'                   => null,
            'recording_enabled'             => $config->recording_enabled,
            'forwarded_to'                  => $config->forwardToPhoneNumber(),
            'duration'                      => null,
        ]);

        $response->assertSee('<Response><Dial answerOnBridge="true" record="do-not-record"><Number>' . $config->forwardToPhoneNumber() . '</Number></Dial></Response>', false);

        Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
            return $company->id === $event->call->company_id && $event->name === Webhook::ACTION_CALL_START;
        });
    }

    /**
     * Test handling an incoming call with recording enabled
     * 
     * @group incoming-calls
     */
    public function testValidIncomingCallWithRecordingEnabled()
    {
        Event::fake();

        $company     = $this->createCompany();
        $config      = $this->createConfig($company, [
            'recording_enabled'      => true,
            'greeting_enabled'       => false,
            'keypress_enabled'       => false,
            'whisper_enabled'        => false
        ]);
        $phoneNumber = $this->createPhoneNumber($company, $config);

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $phoneNumber->e164Format()
        ]);
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');

        $this->assertDatabaseHas('contacts', [
            'country_code' => PhoneNumber::countryCode($incomingCall->From),
            'number' =>  PhoneNumber::number($incomingCall->From)
        ]);

        $this->assertDatabaseHas('calls', [
            'account_id'                    => $this->account->id,
            'company_id'                    => $company->id,
            'keyword_tracking_pool_id'      => null,
            'keyword_tracking_pool_name'    => null,
            'phone_number_id'               => $phoneNumber->id,
            'phone_number_name'             => $phoneNumber->name,
            'type'                          => $phoneNumber->type,
            'category'                      => $phoneNumber->category,
            'sub_category'                  => $phoneNumber->sub_category,
            'external_id'                   => $incomingCall->CallSid,
            'direction'                     => ucfirst($incomingCall->Direction),
            'status'                        => ucfirst($incomingCall->CallStatus),
            'source'                        => $phoneNumber->source,
            'medium'                        => $phoneNumber->medium,
            'content'                       => $phoneNumber->content,
            'campaign'                      => $phoneNumber->campaign,
            'keyword'                       => null,
            'is_paid'                       => null,
            'is_organic'                    => null, 
            'is_direct'                     => null,
            'is_referral'                   => null,
            'recording_enabled'             => $config->recording_enabled,
            'forwarded_to'                  => $config->forwardToPhoneNumber(),
            'duration'                      => null,
        ]);

        $response->assertSee('<Response><Dial answerOnBridge="true" record="record-from-ringing-dual" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number>' . $config->forwardToPhoneNumber() . '</Number></Dial></Response>', false);
            
        Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
            return $company->id === $event->call->company_id && $event->name === Webhook::ACTION_CALL_START;
        });
    }

    /**
     * Test handling an incoming call with recording and greeting enabled with Message
     * 
     * @group incoming-calls
     */
    public function testValidIncomingCallWithRecordingAndGreetingEnabledMessage()
    {
        Event::fake();

        $company     = $this->createCompany();
        $config      = $this->createConfig($company, [
            'recording_enabled'      => true,
            'greeting_enabled'       => true,
            'greeting_message_type'  => 'TEXT',
            'greeting_message'       => 'hello ${caller_first_name} ${caller_last_name}',
            'greeting_audio_clip_id' => null,
            'keypress_enabled'       => false,
            'whisper_enabled'        => false
        ]);
        $phoneNumber = $this->createPhoneNumber($company, $config);

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $phoneNumber->e164Format()
        ]);
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        
        $this->assertDatabaseHas('contacts', [
            'country_code' => PhoneNumber::countryCode($incomingCall->From),
            'number' =>  PhoneNumber::number($incomingCall->From)
        ]);

        $this->assertDatabaseHas('calls', [
            'account_id'                    => $this->account->id,
            'company_id'                    => $company->id,
            'keyword_tracking_pool_id'      => null,
            'keyword_tracking_pool_name'    => null,
            'phone_number_id'               => $phoneNumber->id,
            'phone_number_name'             => $phoneNumber->name,
            'type'                          => $phoneNumber->type,
            'category'                      => $phoneNumber->category,
            'sub_category'                  => $phoneNumber->sub_category,
            'external_id'                   => $incomingCall->CallSid,
            'direction'                     => ucfirst($incomingCall->Direction),
            'status'                        => ucfirst($incomingCall->CallStatus),
            'source'                        => $phoneNumber->source,
            'medium'                        => $phoneNumber->medium,
            'content'                       => $phoneNumber->content,
            'campaign'                      => $phoneNumber->campaign,
            'keyword'                       => null,
            'is_paid'                       => null,
            'is_organic'                    => null, 
            'is_direct'                     => null,
            'is_referral'                   => null,
            'recording_enabled'             => $config->recording_enabled,
            'forwarded_to'                  => $config->forwardToPhoneNumber(),
            'duration'                      => null,
        ]);

        $call    = Call::where('external_id', $incomingCall->CallSid)->first();
        $contact = $call->contact;
        
        $response->assertSee('<Response><Say language="' . $company->tts_language . '" voice="Polly.'  . $company->tts_voice . '">hello ' . $contact->first_name . ' ' . $contact->last_name . '</Say><Dial answerOnBridge="true" record="record-from-ringing-dual" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number>' . $config->forwardToPhoneNumber() . '</Number></Dial></Response>', false);

        Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
            return $company->id === $event->call->company_id && $event->name === Webhook::ACTION_CALL_START;
        });
    }

    /**
     * Test handling an incoming call with recording and greeting enabled using an audio clip
     * 
     * @group incoming-calls
     */
    public function testValidIncomingCallWithRecordingAndGreetingEnabledAudioClip()
    {
        Event::fake();

        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company, [
            'recording_enabled'      => true,
            'greeting_enabled'       => true,
            'greeting_message_type'  => 'AUDIO',
            'greeting_audio_clip_id' => $audioClip->id,
            'keypress_enabled'       => false,
            'whisper_enabled'        => false
        ]);
        $phoneNumber = $this->createPhoneNumber($company, $config);

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $phoneNumber->e164Format()
        ]);
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        
        $this->assertDatabaseHas('contacts', [
            'country_code' => PhoneNumber::countryCode($incomingCall->From),
            'number' =>  PhoneNumber::number($incomingCall->From)
        ]);

        $this->assertDatabaseHas('calls', [
            'account_id'                    => $this->account->id,
            'company_id'                    => $company->id,
            'keyword_tracking_pool_id'      => null,
            'keyword_tracking_pool_name'    => null,
            'phone_number_id'               => $phoneNumber->id,
            'phone_number_name'             => $phoneNumber->name,
            'type'                          => $phoneNumber->type,
            'category'                      => $phoneNumber->category,
            'sub_category'                  => $phoneNumber->sub_category,
            'external_id'                   => $incomingCall->CallSid,
            'direction'                     => ucfirst($incomingCall->Direction),
            'status'                        => ucfirst($incomingCall->CallStatus),
            'source'                        => $phoneNumber->source,
            'medium'                        => $phoneNumber->medium,
            'content'                       => $phoneNumber->content,
            'campaign'                      => $phoneNumber->campaign,
            'keyword'                       => null,
            'is_paid'                       => null,
            'is_organic'                    => null, 
            'is_direct'                     => null,
            'is_referral'                   => null,
            'recording_enabled'             => $config->recording_enabled,
            'forwarded_to'                  => $config->forwardToPhoneNumber(),
            'duration'                      => null,
        ]);
        
        $call = Call::where('external_id', $incomingCall->CallSid)->first();
        
        $response->assertSee('<Response><Play>' . $audioClip->url . '</Play><Dial answerOnBridge="true" record="record-from-ringing-dual" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number>' . $config->forwardToPhoneNumber() . '</Number></Dial></Response>', false);
        
        Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
            return $company->id === $event->call->company_id && $event->name === Webhook::ACTION_CALL_START;
        });
    }

    /**
     * Test handling an incoming call with recording, greeting and keypress
     * 
     * @group incoming-calls
     */
    public function testValidIncomingCallWithRecordingAndGreetingAndKeypressEnabled()
    {
        Event::fake();

        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company, [
            'recording_enabled'      => true,
            'greeting_enabled'       => true,
            'greeting_message_type'  => 'AUDIO',
            'greeting_audio_clip_id' => $audioClip->id,
            'keypress_enabled'       => true,
            'keypress_key'           => mt_rand(0,9),
            'keypress_message_type'  => 'TEXT',
            'keypress_message'       => 'please press 1 to continue.'
        ]);
        $phoneNumber = $this->createPhoneNumber($company, $config);

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $phoneNumber->e164Format()
        ]);
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        
        $this->assertDatabaseHas('contacts', [
            'country_code'  => PhoneNumber::countryCode($incomingCall->From),
            'number'        =>  PhoneNumber::number($incomingCall->From)
        ]);

        $this->assertDatabaseHas('calls', [
            'account_id'            => $this->account->id,
            'company_id'            => $company->id,
            'keyword_tracking_pool_id'      => null,
            'keyword_tracking_pool_name'    => null,
            'phone_number_id'       => $phoneNumber->id,
            'phone_number_name'     => $phoneNumber->name,
            'type'                  => $phoneNumber->type,
            'category'              => $phoneNumber->category,
            'sub_category'          => $phoneNumber->sub_category,
            'external_id'           => $incomingCall->CallSid,
            'direction'             => ucfirst($incomingCall->Direction),
            'status'                => ucfirst($incomingCall->CallStatus),
            'source'                => $phoneNumber->source,
            'medium'                => $phoneNumber->medium,
            'content'               => $phoneNumber->content,
            'campaign'              => $phoneNumber->campaign,
            'keyword'                       => null,
            'is_paid'                       => null,
            'is_organic'                    => null, 
            'is_direct'                     => null,
            'is_referral'                   => null,
            'recording_enabled'     => $config->recording_enabled,
            'forwarded_to'          => $config->forwardToPhoneNumber(),
            'duration'              => null,
        ]);

        $call = Call::where('external_id', $incomingCall->CallSid)->first();
        $response->assertSee('<Response><Play>' 
                                . $audioClip->url 
                                . '</Play>'
                                .    '<Gather numDigits="1" timeout="' . $config->keypress_timeout . '" actionOnEmptyResult="true" method="POST" action="' .
                                        htmlspecialchars(route('incoming-call-collect', [
                                            'call_id'                => $call->id, 
                                            'phone_number_config_id' => $config->id, 
                                            'keypress_attempts'      => $config->keypress_attempts,
                                            'keypress_key'           => $config->keypress_key,
                                            'failed_attempts'        => 0 
                                        ])) 
                                . '"/>'
                                . '</Response>', false);

        Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
            return $company->id === $event->call->company_id && $event->name === Webhook::ACTION_CALL_START;
        });
    }

    /**
     * Test handling an incoming call with recording, greeting and whisper
     * 
     * @group incoming-calls
     */
    public function testValidIncomingCallWithRecordingAndGreetingAndWhisperEnabled()
    {
        Event::fake();

        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company, [
            'recording_enabled'      => true,
            'greeting_enabled'       => true,
            'greeting_message_type'  => 'AUDIO',
            'greeting_audio_clip_id' => $audioClip->id,
            'keypress_enabled'       => false,
            'whisper_enabled'        => true,
            'whisper_message'        => 'call from ${caller_number}'
        ]);
        $phoneNumber = $this->createPhoneNumber($company, $config);

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $phoneNumber->e164Format()
        ]);
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        
        $this->assertDatabaseHas('contacts', [
            'country_code' => PhoneNumber::countryCode($incomingCall->From),
            'number' => PhoneNumber::number($incomingCall->From)
        ]);

        $this->assertDatabaseHas('calls', [
            'account_id'            => $this->account->id,
            'company_id'            => $company->id,
            'phone_number_id'       => $phoneNumber->id,
            'phone_number_name'     => $phoneNumber->name,
            'type'                  => $phoneNumber->type,
            'category'              => $phoneNumber->category,
            'sub_category'          => $phoneNumber->sub_category,
            'external_id'           => $incomingCall->CallSid,
            'direction'             => ucfirst($incomingCall->Direction),
            'status'                => ucfirst($incomingCall->CallStatus),
            'source'                => $phoneNumber->source,
            'medium'                => $phoneNumber->medium,
            'content'               => $phoneNumber->content,
            'campaign'              => $phoneNumber->campaign,
            'recording_enabled'     => $config->recording_enabled,
            'forwarded_to'          => $config->forwardToPhoneNumber(),
            'duration'              => null,
        ]);

        $call = Call::where('external_id', $incomingCall->CallSid)->first();

        $response->assertSee('<Response>'
                            .   '<Play>' 
                            .       $audioClip->url 
                            .   '</Play>'
                            .   '<Dial answerOnBridge="true" record="record-from-ringing-dual" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed">'
                            .       '<Number url="'. htmlspecialchars(route('incoming-call-whisper', [
                                        'whisper_message'  => $config->whisperMessage($call),
                                        'whisper_language' => $company->tts_language,
                                        'whisper_voice'    => $company->tts_voice
                                    ])) .'" method="GET">' . $config->forwardToPhoneNumber() . '</Number>'
                            .   '</Dial>'
                            .'</Response>'
                    , false);

        Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
            return $company->id === $event->call->company_id && $event->name === Webhook::ACTION_CALL_START;
        }); 
    }

    /**
     * Test that when a number is deleted, the call is rejected
     * 
     * @group incoming-calls
     */
    public function testDeletedNumberIsRejected()
    {
        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company);
        $phoneNumber = $this->createPhoneNumber($company, $config);

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $phoneNumber->e164Format()
        ]);

        //  Make sure it works at first
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertDontSee('<Response><Reject/></Response>', false);

        $phoneNumber->delete();

        //  Then make sure it's rejected
        Event::fake();

        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee('<Response><Reject/></Response>', false);

        Event::assertNotDispatched(CallEvent::class);
    }

   
    /**
     * Test that calls to disabled phone numbers are rejected
     * 
     * @group incoming-calls
     */
    public function testDisabledPhoneNumbersRejectCalls()
    {
        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company);
        $phoneNumber = $this->createPhoneNumber($company, $config);

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $phoneNumber->e164Format()
        ]);

         //  Make sure it works at first
         $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
         $response->assertStatus(200);
         $response->assertHeader('Content-Type', 'application/xml');
         $response->assertDontSee('<Response><Reject/></Response>', false);
 
         $phoneNumber->disabled_at = now();
         $phoneNumber->save();
 
         //  Then make sure it's rejected
         Event::fake();

         $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
         $response->assertStatus(200);
         $response->assertHeader('Content-Type', 'application/xml');
         $response->assertSee('<Response><Reject/></Response>', false);

         Event::assertNotDispatched(CallEvent::class);
    }

    /**
     * Test blocked calls are rejected
     * 
     * @group incoming-calls
     */
    public function testBlockedPhoneNumbersRejectCalls()
    {
        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company);
        $phoneNumber = $this->createPhoneNumber($company, $config);

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $phoneNumber->e164Format()
        ]);

         //  Make sure it works at first
         $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
         $response->assertStatus(200);
         $response->assertHeader('Content-Type', 'application/xml');
         $response->assertDontSee('<Response><Reject/></Response>', false);
 
         // Add block
         $blockedNumber = factory(BlockedPhoneNumber::class)->create([
            'account_id'  => $company->account_id,
            'country_code'=> PhoneNumber::countryCode($incomingCall->From),
            'number'      => PhoneNumber::number($incomingCall->From),
            'created_by'  => $this->user->id
         ]);
 
         //  Then make sure it's rejected
         Event::fake();

         $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
         $response->assertStatus(200);
         $response->assertHeader('Content-Type', 'application/xml');
         $response->assertSee('<Response><Reject/></Response>', false);

         Event::assertNotDispatched(CallEvent::class);

         // And make sure the blocked call is logged
         $this->assertDatabaseHas('blocked_calls', [
            'blocked_phone_number_id' => $blockedNumber->id,
            'phone_number_id'         => $phoneNumber->id,
         ]);
    }

   
    /**
     * Test call status change
     * 
     * @group incoming-calls
     */
    public function testCallStatusChange()
    {
        Event::fake();

        $company      = $this->createCompany();
        $config       = $this->createConfig($company);
        $phoneNumber  = $this->createPhoneNumber($company, $config);
        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $phoneNumber->e164Format(),
            'CallStatus' => 'completed',
            'CallDuration' => mt_rand(10, 600)
        ]);
        $contact     = $this->createContact($company);
        $call        = $this->createCall($company, [
            'phone_number_id'   => $phoneNumber->id,
            'phone_number_name' => $phoneNumber->name,
            'contact_id'        => $contact->id,
            'external_id'       => $incomingCall->CallSid
        ]);

        $response = $this->json('POST', route('incoming-call-status-changed', $incomingCall->toArray()));
        $response->assertStatus(200);
        
        $call = Call::find($call->id);
        $this->assertEquals($call->status, 'Completed');
        $this->assertEquals($call->duration, $incomingCall->CallDuration);
        
        Event::assertDispatched(CallEvent::class, function(CallEvent $event){
            return $event->name === Webhook::ACTION_CALL_END;    
        });
    }

    /**
     * Test call from existing contact
     * 
     * @group incoming-calls
     */
    public function testCallFromExistingContact()
    {
        Event::fake();

        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company, [
            'recording_enabled'      => true,
            'greeting_enabled'       => true,
            'greeting_message_type'  => 'AUDIO',
            'greeting_audio_clip_id' => $audioClip->id,
            'keypress_enabled'       => false,
            'whisper_enabled'        => true,
            'whisper_message'        => 'call from ${caller_number}'
        ]);
        $phoneNumber = $this->createPhoneNumber($company, $config);

        $contact = factory(Contact::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id
        ]);

        factory(Call::class)->create([
            'account_id'        => $company->account_id,
            'company_id'        => $company->id,
            'contact_id'        => $contact->id,
            'phone_number_id'   => $phoneNumber->id,
            'phone_number_name' => $phoneNumber->name,
        ]);

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'From' => $contact->e164PhoneFormat(),
            'To'   => $phoneNumber->e164Format()
        ]);

        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');

        $this->assertDatabaseHas('contacts', [
            'country_code' => PhoneNumber::countryCode($incomingCall->From),
            'number' => PhoneNumber::number($incomingCall->From)
        ]);

        $this->assertDatabaseHas('calls', [
            'account_id'            => $this->account->id,
            'company_id'            => $company->id,
            'contact_id'            => $contact->id,
            'phone_number_id'       => $phoneNumber->id,
            'phone_number_name'     => $phoneNumber->name,
            'type'                  => $phoneNumber->type,
            'category'              => $phoneNumber->category,
            'sub_category'          => $phoneNumber->sub_category,
            'external_id'           => $incomingCall->CallSid,
            'direction'             => ucfirst($incomingCall->Direction),
            'status'                => ucfirst($incomingCall->CallStatus),
            'source'                => $phoneNumber->source,
            'medium'                => $phoneNumber->medium,
            'content'               => $phoneNumber->content,
            'campaign'              => $phoneNumber->campaign,
            'recording_enabled'     => $config->recording_enabled,
            'forwarded_to'          => $config->forwardToPhoneNumber(),
            'duration'              => null,
            'first_call'            => false
        ]);

        $call = Call::where('external_id', $incomingCall->CallSid)->first();

        $response->assertSee('<Response>'
                            .   '<Play>' 
                            .       $audioClip->url 
                            .   '</Play>'
                            .   '<Dial answerOnBridge="true" record="record-from-ringing-dual" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed">'
                            .       '<Number url="'. htmlspecialchars(route('incoming-call-whisper', [
                                        'whisper_message'  => $config->whisperMessage($call),
                                        'whisper_language' => $company->tts_language,
                                        'whisper_voice'    => $company->tts_voice
                                    ])) .'" method="GET">' . $config->forwardToPhoneNumber() . '</Number>'
                            .   '</Dial>'
                            .'</Response>'
                    , false);

        Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
            return $company->id === $event->call->company_id && $event->name === Webhook::ACTION_CALL_START;
        }); 
    }

    /**
     *  Test collecting digits
     *  
     *  @group incoming-calls
     */
    public function testCollect()
    {
        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company, [
           'greeting_enabled'      => false,
           'whisper_enabled'       => false,
           'keypress_enabled'      => true,
           'keypress_message_type' => 'TEXT',
           'keypress_message'      => 'Invalid Entry. Please press 5 to continue.',
           'keypress_key'          => 5,
           'keypress_attempts'     => 3
        ]);

        $phoneNumber = $this->createPhoneNumber($company, $config);
        $contact     = factory(Contact::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id
        ]);

        $call = factory(Call::class)->create([
            'account_id'        => $company->account_id,
            'company_id'        => $company->id,
            'contact_id'        => $contact->id,
            'phone_number_id'   => $phoneNumber->id,
            'phone_number_name' => $phoneNumber->name,
        ]);


        //
        //  Try with failure
        //
        for( $failedAttempts= 0; $failedAttempts < $config->keypress_attempts -1; $failedAttempts++ ){
            $response = $this->post(route('incoming-call-collect', [
                'call_id'                => $call->id,
                'phone_number_config_id' => $config->id,
                'keypress_attempts'      => $config->keypress_attempts,
                'keypress_key'           => $config->keypress_key,
                'failed_attempts'        => $failedAttempts,
                'AccountSid'             => config('services.twilio.sid'),
                'Digits'                 => 4
            ]));

            $response->assertSee(
                '<Response>'
                     . '<Gather numDigits="1" timeout="' . $config->keypress_timeout . '" actionOnEmptyResult="true" method="POST" action="' . htmlspecialchars(route('incoming-call-collect', [
                                'call_id'                => $call->id,
                                'phone_number_config_id' => $config->id,
                                'keypress_attempts'      => $config->keypress_attempts,
                                'keypress_key'           => $config->keypress_key,
                                'failed_attempts'        => $failedAttempts + 1
                            ])). '">'
                            .'<Say language="' . $company->tts_language . '" voice="Polly.' .$company->tts_voice . '">' . $config->keypress_message . '</Say>'
                     . '</Gather></Response>'
            , false);

            $response->assertHeader('Content-Type', 'application/xml');
            $response->assertStatus(200);
        }

        $response = $this->post(route('incoming-call-collect', [
            'call_id'                => $call->id,
            'phone_number_config_id' => $config->id,
            'keypress_attempts'      => $config->keypress_attempts,
            'keypress_key'           => $config->keypress_key,
            'failed_attempts'        => $config->keypress_attempts - 1,
            'AccountSid'             => config('services.twilio.sid'),
            'Digits'                 => 4
        ]));

        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee(
            '<Response>'
                 . '<Reject/>'
            . '</Response>'
        , false);

        $response->assertStatus(200);
    }

    /**
     *  Test whisper
     *  
     *  @group incoming-calls
     */
    public function testWhisper()
    {
        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company, [
            'whisper_enabled'  => true,
            'whisper_message'  => 'call from ${source} ${medium} ${content} ${campaign} ${keyword} ${caller_first_name} ${caller_last_name} ${caller_country_code} ${caller_number} ${caller_city} ${caller_state} ${caller_zip} ${caller_country} ${forward_number} ${dialed_number}'
        ]);

        $phoneNumber = $this->createPhoneNumber($company, $config);
        $contact     = factory(Contact::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id
        ]);

        $call = factory(Call::class)->create([
            'account_id'        => $company->account_id,
            'company_id'        => $company->id,
            'contact_id'        => $contact->id,
            'phone_number_id'   => $phoneNumber->id,
            'phone_number_name' => $phoneNumber->name,
        ]);

        $response = $this->get(route('incoming-call-whisper', [
            'whisper_message'  => $config->whisperMessage($call),
            'whisper_language' => $company->tts_language,
            'whisper_voice'    => $company->tts_voice,
            'AccountSid'       => config('services.twilio.sid')
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee(
            '<Response>'
                . '<Say language="' . $company->tts_language . '" voice="Polly.' . $company->tts_voice . '">'
                        . 'call from ' . $call->source . ' ' 
                        . $call->medium . ' ' 
                        . $call->content . ' ' 
                        . $call->campaign . ' ' 
                        . $call->keyword . ' ' 
                        . $contact->first_name . ' ' 
                        . $contact->last_name . ' '
                        . $contact->country_code . ' ' 
                        . $contact->number . ' ' 
                        . $contact->city . ' '
                        . $contact->state . ' ' 
                        . $contact->zip . ' ' 
                        . $contact->country . ' '
                        . $call->forwarded_to . ' '
                        . $phoneNumber->name
                . '</Say>'
            . '</Response>', false);
    }


    /**
     * Test handling a completed recording
     * 
     * @group incoming-calls
     */
    public function testRecordingAvailable()
    {
        Storage::fake();

        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company, [
            'recording_enabled'     => true,
            'transcription_enabled' => true,
        ]);

        $phoneNumber = $this->createPhoneNumber($company, $config);
        $contact     = factory(Contact::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id
        ]);

        $call = factory(Call::class)->create([
            'account_id'        => $company->account_id,
            'company_id'        => $company->id,
            'contact_id'        => $contact->id,
            'phone_number_id'   => $phoneNumber->id,
            'phone_number_name' => $phoneNumber->name,
        ]);

        $url                = $this->faker()->url;
        $duration           = mt_rand(10, 9999);
        $recordingSid       = str_random(40);
        $recordingContent   = random_bytes(9999);

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

        $recordingPath     = 'accounts/' . $call->account_id . '/companies/' . $call->company_id . '/recordings/Call-' . $call->id . '.mp3';
        $transcriptionPath = 'accounts/' . $call->account_id . '/companies/' . $call->company_id . '/transcriptions/Transcription-' . $call->id . '.json';
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
/*
        Storage::assertExists($recordingPath);
        Storage::assertExists($transcriptionPath);
        
        $this->assertDatabaseHas('call_recordings', [
            'call_id'               => $call->id,
            'external_id'           => $recordingSid,
            'path'                  => $recordingPath,
            'transcription_path'    => $transcriptionPath,
            'duration'              => $duration
        ]);*/
    }

    /**
     * Test that calls to disabled keyword tracking pools are rejected
     * 
     * @group incoming-calls
     */
    public function testDisabledKeywordTrackingPoolsRejectCalls()
    {
        Event::fake();

        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company);
        $pool        = factory(KeywordTrackingPool::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by'    => $this->user->id,
            'disabled_at'   => now()
        ]);

        $phoneNumber = factory(PhoneNumber::class, 10)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by'    => $this->user->id,
            'keyword_tracking_pool_id' => $pool->id
        ])->first();

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $phoneNumber->e164Format()
        ]);
 
        //  Make sure it's rejected
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee('<Response><Reject/></Response>', false);

        Event::assertNotDispatched(CallEvent::class);
    }

    /**
     * Test phone number pool call with no session or existing call is rejected
     *
     * @group incoming-calls
     */
    public function testKeywordTrackingPoolCallWithNoSessionAndNoContactIsRejected()
    {
        Event::fake();

        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company);

        $pool        = factory(KeywordTrackingPool::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by'    => $this->user->id
        ]);

        $phoneNumber = factory(PhoneNumber::class, 10)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by'    => $this->user->id,
            'keyword_tracking_pool_id' => $pool->id
        ])->first();

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $phoneNumber->e164Format()
        ]);
 
        //  Make sure it's rejected
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee('<Response><Reject/></Response>', false);

        Event::assertNotDispatched(CallEvent::class);
    }

    /**
     * Test phone number first pool call with active session
     *
     * @group incoming-calls
     */
    public function testFirstPoolCallWithSession()
    {
        Event::fake();

        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company);

        $pool = factory(KeywordTrackingPool::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by'    => $this->user->id
        ]);

        $phoneNumber = factory(PhoneNumber::class, 10)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by'    => $this->user->id,
            'keyword_tracking_pool_id' => $pool->id
        ])->first();

        $params = [
            'utm_source'    => str_random(40), 
            'utm_medium'    => str_random(40), 
            'utm_content'   => str_random(40), 
            'utm_campaign'  => str_random(40), 
            'utm_term'      => str_random(40), 
        ];
        $session = factory(KeywordTrackingPoolSession::class, 5)->create([
            'keyword_tracking_pool_id'  => $pool->id,
            'phone_number_id'           => $phoneNumber->id,
            'landing_url'               => 'http://' . str_random(10) . '.com?' . http_build_query($params),
        ])->last();

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $phoneNumber->e164Format()
        ]);
 
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        
        $contact = Contact::where('country_code', PhoneNumber::countryCode($incomingCall->From))
                          ->where('number', PhoneNumber::number($incomingCall->From))
                          ->first();

        $this->assertNotNull($contact);
        $this->assertDatabaseHas('calls', [
            'keyword_tracking_pool_id' => $pool->id,
            'keyword_tracking_pool_name' => $pool->name,
            'account_id'            => $this->account->id,
            'company_id'            => $company->id,
            'contact_id'            => $contact->id,
            'phone_number_id'       => $phoneNumber->id,
            'phone_number_name'     => $phoneNumber->name,
            'type'                  => $phoneNumber->type,
            'category'              => $phoneNumber->category,
            'sub_category'          => $phoneNumber->sub_category,
            'external_id'           => $incomingCall->CallSid,
            'direction'             => ucfirst($incomingCall->Direction),
            'status'                => ucfirst($incomingCall->CallStatus),
            'source'                => $session->getSource($company->source_param, $company->source_referrer_when_empty),
            'medium'                => $session->getMedium($company->medium_param),
            'content'               => $session->getContent($company->content_param),
            'campaign'              => $session->getCampaign($company->campaign_param),
            'keyword'               => $session->getKeyword($company->keyword_param),
            'is_paid'               => $session->getIsPaid($company->medium_param),
            'is_organic'            => $session->getIsOrganic($company->medium_param),
            'is_direct'             => $session->getIsDirect(),
            'is_referral'           => $session->getIsReferral(),
            'recording_enabled'     => $config->recording_enabled,
            'forwarded_to'          => $config->forwardToPhoneNumber(),
            'duration'              => null,
        ]);

        $this->assertEquals($params['utm_source'],  $session->getSource($company->source_param, $company->source_referrer_when_empty));
        $this->assertEquals($params['utm_medium'], $session->getMedium($company->medium_param));
        $this->assertEquals($params['utm_content'], $session->getContent($company->content_param));
        $this->assertEquals($params['utm_campaign'], $session->getCampaign($company->campaign_param));
        $this->assertEquals($params['utm_term'], $session->getKeyword($company->keyword_param));
        $this->assertFalse($session->getIsOrganic($company->medium_param));
        $this->assertFalse($session->getIsPaid($company->medium_param));
        $this->assertFalse($session->getIsDirect());
        $this->assertTrue($session->getIsReferral());

        $this->assertDatabaseHas('keyword_tracking_pool_sessions', [
            'id'         => $session->id,
            'contact_id' => $contact->id
        ]);

        Event::assertDispatched(CallEvent::class);
    }

    /**
     * Test phone number second pool call with active session
     *
     * @group incoming-calls
     */
    public function testSecondPoolCallWithSession()
    {
        Event::fake();

        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company);

        $pool = factory(KeywordTrackingPool::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by'    => $this->user->id
        ]);

        $phoneNumber = factory(PhoneNumber::class, 10)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by'    => $this->user->id,
            'keyword_tracking_pool_id' => $pool->id
        ])->first();

        $contact = factory(Contact::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
        ]);

        $params1 = [
            'utm_source'    => str_random(40), 
            'utm_medium'    => str_random(40), 
            'utm_content'   => str_random(40), 
            'utm_campaign'  => str_random(40), 
            'utm_term'      => str_random(40), 
        ];
        $session1 = factory(KeywordTrackingPoolSession::class)->create([
            'keyword_tracking_pool_id'  => $pool->id,
            'phone_number_id'           => $phoneNumber->id,
            'contact_id'                => $contact->id,
            'landing_url'               => 'http://' . str_random(10) . '.com?' . http_build_query($params1),
            'ended_at'                  => now()
        ]);

        $params2 = [
            'utm_source'    => str_random(40), 
            'utm_medium'    => str_random(40), 
            'utm_content'   => str_random(40), 
            'utm_campaign'  => str_random(40), 
            'utm_term'      => str_random(40), 
        ];
        $session2 = factory(KeywordTrackingPoolSession::class)->create([
            'keyword_tracking_pool_id'  => $pool->id,
            'phone_number_id'           => $phoneNumber->id,
            'contact_id'                => $contact->id,
            'landing_url'               => 'http://' . str_random(10) . '.com?' . http_build_query($params2),
        ]);

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'From' => $contact->e164PhoneFormat(),
            'To'   => $phoneNumber->e164Format()
        ]);
 
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        
        $this->assertDatabaseHas('calls', [
            'keyword_tracking_pool_id' => $pool->id,
            'keyword_tracking_pool_name' => $pool->name,
            'account_id'            => $this->account->id,
            'company_id'            => $company->id,
            'contact_id'            => $contact->id,
            'phone_number_id'       => $phoneNumber->id,
            'phone_number_name'     => $phoneNumber->name,
            'type'                  => $phoneNumber->type,
            'category'              => $phoneNumber->category,
            'sub_category'          => $phoneNumber->sub_category,
            'external_id'           => $incomingCall->CallSid,
            'direction'             => ucfirst($incomingCall->Direction),
            'status'                => ucfirst($incomingCall->CallStatus),
            'source'                => $session2->getSource($company->source_param, $company->source_referrer_when_empty),
            'medium'                => $session2->getMedium($company->medium_param),
            'content'               => $session2->getContent($company->content_param),
            'campaign'              => $session2->getCampaign($company->campaign_param),
            'keyword'               => $session2->getKeyword($company->keyword_param),
            'is_paid'               => $session2->getIsPaid($company->medium_param),
            'is_organic'            => $session2->getIsOrganic($company->medium_param),
            'is_direct'             => $session2->getIsDirect(),
            'is_referral'           => $session2->getIsReferral(),
            'recording_enabled'     => $config->recording_enabled,
            'forwarded_to'          => $config->forwardToPhoneNumber(),
            'duration'              => null,
        ]);

        $this->assertEquals($params2['utm_source'],  $session2->getSource($company->source_param, $company->source_referrer_when_empty));
        $this->assertEquals($params2['utm_medium'], $session2->getMedium($company->medium_param));
        $this->assertEquals($params2['utm_content'], $session2->getContent($company->content_param));
        $this->assertEquals($params2['utm_campaign'], $session2->getCampaign($company->campaign_param));
        $this->assertEquals($params2['utm_term'], $session2->getKeyword($company->keyword_param));
        $this->assertFalse($session2->getIsOrganic($company->medium_param));
        $this->assertFalse($session2->getIsPaid($company->medium_param));
        $this->assertFalse($session2->getIsDirect());
        $this->assertTrue($session2->getIsReferral());

        $this->assertDatabaseHas('keyword_tracking_pool_sessions', [
            'id'         => $session2->id,
            'contact_id' => $contact->id
        ]);

        Event::assertDispatched(CallEvent::class);
    }

    /**
     * Test calling a pool with a new UUID session
     *
     * @group incoming-calls
     */
    public function testSecondPoolCallWithNewUUID()
    {
        Event::fake();

        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company);

        $pool = factory(KeywordTrackingPool::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by'    => $this->user->id
        ]);

        $phoneNumber = factory(PhoneNumber::class, 10)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by'    => $this->user->id,
            'keyword_tracking_pool_id' => $pool->id
        ])->first();

        $contact = factory(Contact::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
        ]);

        $params1 = [
            'utm_source'    => str_random(40), 
            'utm_medium'    => str_random(40), 
            'utm_content'   => str_random(40), 
            'utm_campaign'  => str_random(40), 
            'utm_term'      => str_random(40), 
        ];
        $session1 = factory(KeywordTrackingPoolSession::class)->create([
            'keyword_tracking_pool_id'  => $pool->id,
            'phone_number_id'           => $phoneNumber->id,
            'contact_id'                => $contact->id,
            'landing_url'               => 'http://' . str_random(10) . '.com?' . http_build_query($params1),
            'ended_at'                  => now()
        ]);

        $params2 = [
            'utm_source'    => str_random(40), 
            'utm_medium'    => str_random(40), 
            'utm_content'   => str_random(40), 
            'utm_campaign'  => str_random(40), 
            'utm_term'      => str_random(40), 
        ];
        $session2 = factory(KeywordTrackingPoolSession::class)->create([
            'contact_id'                => null,
            'keyword_tracking_pool_id'  => $pool->id,
            'phone_number_id'           => $phoneNumber->id,
            'landing_url'               => 'http://' . str_random(10) . '.com?' . http_build_query($params2),
        ]);

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'From' => $contact->e164PhoneFormat(),
            'To'   => $phoneNumber->e164Format()
        ]);
 
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        
        $this->assertDatabaseHas('calls', [
            'keyword_tracking_pool_id' => $pool->id,
            'keyword_tracking_pool_name' => $pool->name,
            'account_id'            => $this->account->id,
            'company_id'            => $company->id,
            'contact_id'            => $contact->id,
            'phone_number_id'       => $phoneNumber->id,
            'phone_number_name'     => $phoneNumber->name,
            'type'                  => $phoneNumber->type,
            'category'              => $phoneNumber->category,
            'sub_category'          => $phoneNumber->sub_category,
            'external_id'           => $incomingCall->CallSid,
            'direction'             => ucfirst($incomingCall->Direction),
            'status'                => ucfirst($incomingCall->CallStatus),
            'source'                => $session2->getSource($company->source_param, $company->source_referrer_when_empty),
            'medium'                => $session2->getMedium($company->medium_param),
            'content'               => $session2->getContent($company->content_param),
            'campaign'              => $session2->getCampaign($company->campaign_param),
            'keyword'               => $session2->getKeyword($company->keyword_param),
            'is_paid'               => $session2->getIsPaid($company->medium_param),
            'is_organic'            => $session2->getIsOrganic($company->medium_param),
            'is_direct'             => $session2->getIsDirect(),
            'is_referral'           => $session2->getIsReferral(),
            'recording_enabled'     => $config->recording_enabled,
            'forwarded_to'          => $config->forwardToPhoneNumber(),
            'duration'              => null,
        ]);

        $this->assertEquals($params2['utm_source'],  $session2->getSource($company->source_param, $company->source_referrer_when_empty));
        $this->assertEquals($params2['utm_medium'], $session2->getMedium($company->medium_param));
        $this->assertEquals($params2['utm_content'], $session2->getContent($company->content_param));
        $this->assertEquals($params2['utm_campaign'], $session2->getCampaign($company->campaign_param));
        $this->assertEquals($params2['utm_term'], $session2->getKeyword($company->keyword_param));
        $this->assertFalse($session2->getIsOrganic($company->medium_param));
        $this->assertFalse($session2->getIsPaid($company->medium_param));
        $this->assertFalse($session2->getIsDirect());
        $this->assertTrue($session2->getIsReferral());

        $this->assertDatabaseHas('keyword_tracking_pool_sessions', [
            'id'         => $session2->id,
            'contact_id' => $contact->id
        ]);

        Event::assertDispatched(CallEvent::class);
    }

    /**
     * Test calling a pool with no active session
     *
     * @group incoming-calls
     */
    public function testSecondPoolCallWithNoSession()
    {
        Event::fake();

        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company);

        $pool = factory(KeywordTrackingPool::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by'    => $this->user->id
        ]);

        $phoneNumber = factory(PhoneNumber::class, 10)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'created_by'    => $this->user->id,
            'keyword_tracking_pool_id' => $pool->id
        ])->first();

        $contact = factory(Contact::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
        ]);

        $params1 = [
            'utm_source'    => str_random(40), 
            'utm_medium'    => str_random(40), 
            'utm_content'   => str_random(40), 
            'utm_campaign'  => str_random(40), 
            'utm_term'      => str_random(40), 
        ];
        $session1 = factory(KeywordTrackingPoolSession::class)->create([
            'keyword_tracking_pool_id'  => $pool->id,
            'phone_number_id'           => $phoneNumber->id,
            'contact_id'                => $contact->id,
            'landing_url'               => 'http://' . str_random(10) . '.com?' . http_build_query($params1),
            'ended_at'                  => now()
        ]);

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'From' => $contact->e164PhoneFormat(),
            'To'   => $phoneNumber->e164Format()
        ]);
 
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        
        $this->assertDatabaseHas('calls', [
            'keyword_tracking_pool_id' => $pool->id,
            'keyword_tracking_pool_name' => $pool->name,
            'account_id'            => $this->account->id,
            'company_id'            => $company->id,
            'contact_id'            => $contact->id,
            'phone_number_id'       => $phoneNumber->id,
            'phone_number_name'     => $phoneNumber->name,
            'type'                  => $phoneNumber->type,
            'category'              => $phoneNumber->category,
            'sub_category'          => $phoneNumber->sub_category,
            'external_id'           => $incomingCall->CallSid,
            'direction'             => ucfirst($incomingCall->Direction),
            'status'                => ucfirst($incomingCall->CallStatus),
            'source'                => null,
            'medium'                => null,
            'content'               => null,
            'campaign'              => null,
            'keyword'               => null,
            'is_paid'               => null,
            'is_organic'            => null,
            'is_direct'             => null,
            'is_referral'           => null,
            'recording_enabled'     => $config->recording_enabled,
            'forwarded_to'          => $config->forwardToPhoneNumber(),
            'duration'              => null,
        ]);

        Event::assertDispatched(CallEvent::class);
    }
}
