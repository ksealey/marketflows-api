<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use \App\Models\AccountBlockedPhoneNumber;
use \App\Models\BankedPhoneNumber;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\BlockedPhoneNumber;
use \App\Models\Company\Call;

class IncomingCallTest extends TestCase
{
    use \Tests\CreatesAccount;
    /**
     * Test handling an incoming call with no options
     * 
     * @group incoming-calls--
     */
    public function testValidIncomingCallWithNoOptions()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company, [
            'recording_enabled'      => false,
            'greeting_message'       => null,
            'greeting_audio_clip_id' => null,
            'keypress_enabled'       => 0,
            'whisper_message'        => null,
            'keypress_key'           => 1
        ]);
        $phoneNumber = $this->createPhoneNumber($company, $config);

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $phoneNumber->e164Format()
        ]);
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
        $response->assertHeader('Content-Type', 'application/xml');
        
        $response->assertStatus(200);

        $this->assertDatabaseHas('contacts', [
            'phone' => preg_replace('/[^0-9]+/', '', $incomingCall->From)
        ]);

        $this->assertDatabaseHas('calls', [
            'account_id'            => $this->account->id,
            'company_id'            => $company->id,
            'phone_number_id'       => $phoneNumber->id,
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

        $response->assertSee('<Response><Dial answerOnBridge="true" record="do-not-record"><Number>' . $config->forwardToPhoneNumber() . '</Number></Dial></Response>', false);
        
    }

    /**
     * Test handling an incoming call with recording enabled
     * 
     * @group incoming-calls--
     */
    public function testValidIncomingCallWithRecordingEnabled()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company, [
            'recording_enabled'      => true,
            'greeting_message'       => null,
            'greeting_audio_clip_id' => null,
            'keypress_enabled'       => 0,
            'whisper_message'        => null,
            'keypress_key'           => 1
        ]);
        $phoneNumber = $this->createPhoneNumber($company, $config);

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $phoneNumber->e164Format()
        ]);
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');

        $this->assertDatabaseHas('contacts', [
            'phone' => preg_replace('/[^0-9]+/', '', $incomingCall->From)
        ]);

        $this->assertDatabaseHas('calls', [
            'account_id'            => $this->account->id,
            'company_id'            => $company->id,
            'phone_number_id'       => $phoneNumber->id,
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

        $response->assertSee('<Response><Dial answerOnBridge="true" record="record-from-ringing-dual" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number>' . $config->forwardToPhoneNumber() . '</Number></Dial></Response>', false);
    }

    /**
     * Test handling an incoming call with recording and greeting enabled with Message
     * 
     * @group incoming-calls--
     */
    public function testValidIncomingCallWithRecordingAndGreetingEnabledMessage()
    {
        $company     = $this->createCompany();
        $config      = $this->createConfig($company, [
            'recording_enabled'      => true,
            'greeting_enabled'       => true,
            'greeting_message'       => 'hello ${caller_first_name} ${caller_last_name}',
            'greeting_audio_clip_id' => null,
            'keypress_enabled'       => 0,
            'whisper_message'        => null,
            'keypress_key'           => 1
        ]);
        $phoneNumber = $this->createPhoneNumber($company, $config);

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $phoneNumber->e164Format()
        ]);
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        
        $this->assertDatabaseHas('contacts', [
            'phone' => preg_replace('/[^0-9]+/', '', $incomingCall->From)
        ]);

        $this->assertDatabaseHas('calls', [
            'account_id'            => $this->account->id,
            'company_id'            => $company->id,
            'phone_number_id'       => $phoneNumber->id,
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

        $call    = Call::where('external_id', $incomingCall->CallSid)->first();
        $contact = $call->contact;
        
        $response->assertSee('<Response><Say language="' . $company->tts_language . '" voice="Polly.'  . $company->tts_voice . '">hello ' . $contact->first_name . ' ' . $contact->last_name . '</Say><Dial answerOnBridge="true" record="record-from-ringing-dual" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number>' . $config->forwardToPhoneNumber() . '</Number></Dial></Response>', false);

    }

    /**
     * Test handling an incoming call with recording and greeting enabled using an audio clip
     * 
     * @group incoming-calls--
     */
    public function testValidIncomingCallWithRecordingAndGreetingEnabledAudioClip()
    {
        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company, [
            'recording_enabled'      => true,
            'greeting_enabled'       => true,
            'greeting_message'       => 'hello',
            'greeting_audio_clip_id' => $audioClip->id,
            'keypress_enabled'       => 0,
            'whisper_message'        => null,
            'keypress_key'           => 1
        ]);
        $phoneNumber = $this->createPhoneNumber($company, $config);

        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $phoneNumber->e164Format()
        ]);
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        
        $this->assertDatabaseHas('contacts', [
            'phone' => preg_replace('/[^0-9]+/', '', $incomingCall->From)
        ]);

        $this->assertDatabaseHas('calls', [
            'account_id'            => $this->account->id,
            'company_id'            => $company->id,
            'phone_number_id'       => $phoneNumber->id,
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
        
        $response->assertSee('<Response><Play>' . $audioClip->url . '</Play><Dial answerOnBridge="true" record="record-from-ringing-dual" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number>' . $config->forwardToPhoneNumber() . '</Number></Dial></Response>', false);
        
    }

    /**
     * Test handling an incoming call with recording, greeting and keypress
     * 
     * @group incoming-calls--
     */
    public function testValidIncomingCallWithRecordingAndGreetingAndKeypressEnabled()
    {
        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company, [
            'recording_enabled'      => true,
            'greeting_enabled'       => true,
            'greeting_audio_clip_id' => $audioClip->id,
            'keypress_enabled'       => 1,
            'keypress_key'           => mt_rand(0,9),
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
            'phone' => preg_replace('/[^0-9]+/', '', $incomingCall->From)
        ]);

        $this->assertDatabaseHas('calls', [
            'account_id'            => $this->account->id,
            'company_id'            => $company->id,
            'phone_number_id'       => $phoneNumber->id,
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
        $response->assertSee('<Response><Play>' 
                                . $audioClip->url 
                                . '</Play>'
                                .    '<Gather numDigits="1">'
                                .        '<Say language="' . $company->tts_language . '" voice="Polly.'  . $company->tts_voice . '">' 
                                .           $config->keypress_message 
                                .       '</Say>'
                                .    '</Gather>'
                                .    '<Redirect>' 
                                .    htmlspecialchars(route('incoming-call-collect', [
                                        'call_id'                => $call->id, 
                                        'phone_number_config_id' => $config->id, 
                                        'attempts'               => 1 
                                     ]))
                                .    '</Redirect>'
                                . '</Response>', false);
        
    }

    /**
     * Test handling an incoming call with recording, greeting and whisper
     * 
     * @group incoming-calls--
     */
    public function testValidIncomingCallWithRecordingAndGreetingAndWhisperEnabled()
    {
        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company, [
            'recording_enabled'      => true,
            'greeting_enabled'       => true,
            'greeting_audio_clip_id' => $audioClip->id,
            'keypress_enabled'       => false,
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
            'phone' => preg_replace('/[^0-9]+/', '', $incomingCall->From)
        ]);

        $this->assertDatabaseHas('calls', [
            'account_id'            => $this->account->id,
            'company_id'            => $company->id,
            'phone_number_id'       => $phoneNumber->id,
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
        
    }

    /**
     * Test that when a number is deleted, the call is rejected
     * 
     * @group incoming-calls--
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
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee('<Response><Reject/></Response>', false);
    }

    /**
     * Test that when a number is deleted and added to the phone number bank, it's call count increases
     * 
     * @group incoming-calls
     */
    public function testDeletedNumberInBankIncrementsCalls()
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
        $bankedNumber= factory(BankedPhoneNumber::class)->create([
            'released_by_account_id' => $this->account->id,
            'country_code'           => $phoneNumber->country_code,
            'number'                 => $phoneNumber->number
        ]);

        //  Then make sure its rejected
        for( $i = 0; $i < 2; $i++ ){
            $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
            $response->assertStatus(200);
            $response->assertHeader('Content-Type', 'application/xml');
            $response->assertSee('<Response><Reject/></Response>', false);

            //  And the count has incremented
            $callCount = BankedPhoneNumber::find($bankedNumber->id)->calls;
            $this->assertEquals($callCount, $i+1);
        }
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
         $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
         $response->assertStatus(200);
         $response->assertHeader('Content-Type', 'application/xml');
         $response->assertSee('<Response><Reject/></Response>', false);
    }

    /**
     * Test blocked calls at company level are rejected
     * 
     * @group incoming-calls
     */
    public function testCompanyBlockedPhoneNumbersRejectCalls()
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
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'country_code'=> PhoneNumber::countryCode($incomingCall->From),
            'number'     => PhoneNumber::number($incomingCall->From),
            'created_by' => $this->user->id
         ]);
 
         //  Then make sure it's rejected
         $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
         $response->assertStatus(200);
         $response->assertHeader('Content-Type', 'application/xml');
         $response->assertSee('<Response><Reject/></Response>', false);

         // And make sure the blocked call is logged
         $this->assertDatabaseHas('blocked_calls', [
            'blocked_phone_number_id' => $blockedNumber->id,
            'phone_number_id'         => $phoneNumber->id,
         ]);
    }

    /**
     * Test blocked calls at account level are rejected
     * 
     * @group incoming-calls
     */
    public function testAccountBlockedPhoneNumbersRejectCalls()
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
 
         // Add account number block
         $blockedNumber = factory(AccountBlockedPhoneNumber::class)->create([
            'account_id'   => $company->account_id,
            'created_by'   => $this->user->id,
            'country_code' => PhoneNumber::countryCode($incomingCall->From),
            'number'       => PhoneNumber::number($incomingCall->From)
         ]);
 
         //  Then make sure it's rejected
         $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
         $response->assertStatus(200);
         $response->assertHeader('Content-Type', 'application/xml');
         $response->assertSee('<Response><Reject/></Response>', false);

         // And make sure the blocked call is logged
         $this->assertDatabaseHas('account_blocked_calls', [
            'account_blocked_phone_number_id' => $blockedNumber->id,
            'phone_number_id'                 => $phoneNumber->id,
         ]);
    }
}
