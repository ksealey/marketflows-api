<?php

namespace Tests\Feature\Incoming;

use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumberPool;
use App\Models\Company\AudioClip;
use App\Models\Company\Campaign;
use App\Models\Company\WebhookCall;
use App\Events\IncomingCallEvent;
use App\Events\IncomingCallUpdatedEvent;
use App\Models\Company\PhoneNumber\Call;
use App\Models\Company\PhoneNumber\CallRecording;
use Storage;

class CallTest extends TestCase
{
    use \Tests\CreatesUser, WithFaker;

    /**
     * Test handling an incoming phone call for a print campaign tied to a single phone number
     * 
     * @group incoming-calls
     */
    public function testHandleIncomingPhoneCallForPrintCampaign()
    {
        $this->expectsEvents(IncomingCallEvent::class);

        $config = $this->createPhoneNumberConfig([
            'recording_enabled_at' => null,
            'whisper_message'      => null
        ]);

        $phone          = $this->createPhoneNumber([], $config);
        $incomingCall   = $this->generateIncomingCall($phone->e164Format());

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);
        $response->assertStatus(200);
        $response->assertSee('<Response><Dial answerOnBridge="true" record="do-not-record"><Number>' . $phone->forwardToPhoneNumber() . '</Number></Dial></Response>');

        //
        //  Try again with recording on
        //
        $config->recording_enabled_at = date('Y-m-d H:i:s');
        $config->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall); 
        $response->assertStatus(200);
        $response->assertSee('<Response><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' 
            . route('incoming-call-recording-available') 
            . '" recordingStatusCallbackEvent="completed"><Number>' 
            . $phone->forwardToPhoneNumber() 
            . '</Number></Dial></Response>'
        );
    
        //
        //  Try again with audio clip
        //
        $audioClip = factory(AudioClip::class)->create([
            'company_id' => $phone->company_id,
            'created_by' => $this->user->id,
            'path'       => AudioClip::storagePath($this->company, 'audio_clips') . '/' . mt_rand(99999, 9999999) . '.mp3'
        ]);
        $config->audio_clip_id = $audioClip->id;
        $config->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);
        $response->assertStatus(200);
        $response->assertSee(
            '<Response><Play>' 
            . $audioClip->getURL() 
            . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' 
            . route('incoming-call-recording-available') 
            . '" recordingStatusCallbackEvent="completed"><Number>' 
            . $phone->forwardToPhoneNumber() 
            . '</Number></Dial></Response>'
        );
        
        //
        //  Try again with whisper
        //
        $config->whisper_message  = 'Hello world';
        $config->whisper_language = 'en';
        $config->whisper_voice    = 'alice'; 
        $config->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);
        $response->assertStatus(200);
        $response->assertSee(str_replace('&', '&amp;', 
            '<Response><Play>' . $audioClip->getURL() . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number url="' . route('incoming-call-whisper',[
                'whisper_message' => $config->whisper_message,
                'whisper_language'=> $config->whisper_language,
                'whisper_voice'   => $config->whisper_voice
            ]) . '" method="GET">' . $config->forwardToPhoneNumber() . '</Number></Dial></Response>')
        );
    }

    /**
     * Test handling an incoming phone number pool call for a print campaign 
     * 
     * We want to make sure the actions are being drven by the pool's config changes
     * 
     * @group incoming-calls
     */
    public function testHandleIncomingPhonePoolCallForPrintCampaign()
    {
        $this->expectsEvents(IncomingCallEvent::class);

        $campaign = $this->createCampaign([
            'type' => Campaign::TYPE_PRINT
        ]);

        $config = $this->createPhoneNumberConfig([
            'forward_to_number'    => $this->getTestForwardPhone(),
            'recording_enabled_at' => null,
            'whisper_message'      => null
        ]);

        $pool = $this->createPhoneNumberPool([
            'phone_number_config_id' => $config->id,
            'campaign_id'            => $campaign->id,
        ]);

        $phone = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id
        ], $this->createPhoneNumberConfig());

        $incomingCall = $this->generateIncomingCall($phone->e164Format());

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);
        $response->assertStatus(200);
        $response->assertSee('<Response><Dial answerOnBridge="true" record="do-not-record"><Number>' .$pool->forwardToPhoneNumber() . '</Number></Dial></Response>');

        //
        //  Try again with recording on
        //
        $config->recording_enabled_at = date('Y-m-d H:i:s');
        $config->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);
        $response->assertStatus(200);
        $response->assertSee(
            '<Response><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' 
            . route('incoming-call-recording-available') 
            . '" recordingStatusCallbackEvent="completed"><Number>' 
            . $pool->forwardToPhoneNumber() 
            . '</Number></Dial></Response>'
        );
    
        //
        //  Try again with audio clip
        //
        $audioClip = factory(AudioClip::class)->create([
            'company_id' => $phone->company_id,
            'created_by' => $this->user->id,
            'path'       => AudioClip::storagePath($this->company, 'audio_clips') . '/' . mt_rand(99999, 9999999) . '.mp3'
        ]);
        $config->audio_clip_id = $audioClip->id;
        $config->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);
        $response->assertStatus(200);
        $response->assertSee(
            '<Response><Play>' 
            . $audioClip->getURL() 
            . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' 
            . route('incoming-call-recording-available') 
            . '" recordingStatusCallbackEvent="completed"><Number>' 
            . $config->forwardToPhoneNumber() 
            . '</Number></Dial></Response>'
        );

        //
        //  Try again with whisper
        //
        $config->whisper_message  = 'Hello world';
        $config->whisper_language = 'en';
        $config->whisper_voice    = 'alice'; 
        $config->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);
        $response->assertStatus(200);
        $response->assertSee(str_replace('&', '&amp;', 
            '<Response><Play>' . $audioClip->getURL() . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number url="' . route('incoming-call-whisper',[
                'whisper_message' => $config->whisper_message,
                'whisper_language'=> $config->whisper_language,
                'whisper_voice'   => $config->whisper_voice
            ]) . '" method="GET">' . $config->forwardToPhoneNumber() . '</Number></Dial></Response>')
        );
    }


    /**
     * Test handling an incoming phone call for a radio campaign tied to a single phone number
     * 
     * @group incoming-calls
     */
    public function testHandleIncomingPhoneCallForRadioCampaign()
    {
        $this->expectsEvents(IncomingCallEvent::class);

        $campaign = $this->createCampaign([
            'type' => Campaign::TYPE_RADIO
        ]);

        $config = $this->createPhoneNumberConfig([
            'forward_to_number'    => $this->getTestForwardPhone(),
            'recording_enabled_at' => null,
            'whisper_message'      => null
        ]);

        $phone = $this->createPhoneNumber([], $config);

        $incomingCall = $this->generateIncomingCall($phone->e164Format());

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);

        $response->assertStatus(200);
        $response->assertSee(
            '<Response><Dial answerOnBridge="true" record="do-not-record"><Number>' 
            . $phone->forwardToPhoneNumber() 
            . '</Number></Dial></Response>'
        );

        //
        //  Try again with recording on
        //
        $config->recording_enabled_at = date('Y-m-d H:i:s');
        $config->save();
        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);

        $response->assertStatus(200);
        $response->assertSee(
            '<Response><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' 
            . route('incoming-call-recording-available') 
            . '" recordingStatusCallbackEvent="completed"><Number>' 
            . $phone->forwardToPhoneNumber() 
            . '</Number></Dial></Response>'
        );
    
        //
        //  Try again with audio clip
        //
        $audioClip = factory(AudioClip::class)->create([
            'company_id' => $phone->company_id,
            'created_by' => $this->user->id,
            'path'       => AudioClip::storagePath($this->company, 'audio_clips') . '/' . mt_rand(99999, 9999999) . '.mp3'
        ]);
        $config->audio_clip_id = $audioClip->id;
        $config->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);
        $response->assertStatus(200);
        $response->assertSee(
            '<Response><Play>' 
            . $audioClip->getURL() 
            . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' 
            . route('incoming-call-recording-available') 
            . '" recordingStatusCallbackEvent="completed"><Number>' 
            . $phone->forwardToPhoneNumber() 
            . '</Number></Dial></Response>'
        );
        
        //
        //  Try again with whisper
        //
        $config->whisper_message  = 'Hello world';
        $config->whisper_language = 'en';
        $config->whisper_voice    = 'alice'; 
        $config->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);

        $response->assertStatus(200);
        $response->assertSee(str_replace('&', '&amp;', 
            '<Response><Play>' 
            . $audioClip->getURL() 
            . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' 
            . route('incoming-call-recording-available') 
            . '" recordingStatusCallbackEvent="completed"><Number url="' 
            . route('incoming-call-whisper', [
                'whisper_message' => $config->whisper_message,
                'whisper_language'=> $config->whisper_language,
                'whisper_voice'   => $config->whisper_voice
            ]) 
            . '" method="GET">' 
            . $config->forwardToPhoneNumber() 
            . '</Number></Dial></Response>'
        ));
    }

    /**
     * Test handling an incoming phone number pool call for a radio campaign 
     * 
     * We want to make sure the actions are being drven by the pool's config changes
     * 
     * @group incoming-calls
     */
    public function testHandleIncomingPhonePoolCallForRadioCampaign()
    {
        $this->expectsEvents(IncomingCallEvent::class);

        $campaign = $this->createCampaign([
            'type' => Campaign::TYPE_RADIO
        ]);

        $config = $this->createPhoneNumberConfig([
            'recording_enabled_at' => null,
            'whisper_message'      => null
        ]);

        $pool = $this->createPhoneNumberPool([
            'phone_number_config_id' => $config->id,
            'campaign_id'            => $campaign->id,
        ]);

        $phone = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id
        ], $this->createPhoneNumberConfig());
       
        $incomingCall = $this->generateIncomingCall($phone->e164Format());

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);
        $response->assertStatus(200);
        $response->assertSee(
            '<Response><Dial answerOnBridge="true" record="do-not-record"><Number>' 
            . $pool->forwardToPhoneNumber() 
            . '</Number></Dial></Response>'
        );

        //
        //  Try again with recording on
        //
        $config->recording_enabled_at = date('Y-m-d H:i:s');
        $config->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);
        $response->assertStatus(200);
        $response->assertSee(
            '<Response><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' 
            . route('incoming-call-recording-available') 
            . '" recordingStatusCallbackEvent="completed"><Number>' 
            . $pool->forwardToPhoneNumber() 
            . '</Number></Dial></Response>'
        );
    
        //
        //  Try again with audio clip
        //
        $audioClip = factory(AudioClip::class)->create([
            'company_id' => $phone->company_id,
            'created_by' => $this->user->id,
            'path'       => AudioClip::storagePath($this->company, 'audio_clips') . '/' . mt_rand(99999, 9999999) . '.mp3'
        ]);
        $config->audio_clip_id = $audioClip->id;
        $config->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);

        $response->assertStatus(200);
        $response->assertSee(
            '<Response><Play>' 
            . $audioClip->getURL() 
            . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' 
            . route('incoming-call-recording-available') 
            . '" recordingStatusCallbackEvent="completed"><Number>' 
            . $pool->forwardToPhoneNumber() 
            . '</Number></Dial></Response>'
        );

        //
        //  Try again with whisper
        //
        $config->whisper_message  = 'Hello world';
        $config->whisper_language = 'en';
        $config->whisper_voice    = 'alice'; 
        $config->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);

        $response->assertStatus(200);
        $response->assertSee(str_replace('&', '&amp;', 
            '<Response><Play>' . $audioClip->getURL() . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number url="' . route('incoming-call-whisper',[
                'whisper_message' => $config->whisper_message,
                'whisper_language'=> $config->whisper_language,
                'whisper_voice'   => $config->whisper_voice
            ]) . '" method="GET">' . $config->forwardToPhoneNumber() . '</Number></Dial></Response>')
        );
    }

    /**
     * Test handling an incoming phone number pool call for a web campaign 
     * 
     * @group incoming-calls
     */
    public function testHandleIncomingPhonePoolCallForWebCampaign()
    {
        $this->expectsEvents(IncomingCallEvent::class);

        $campaign = $this->createCampaign([
            'type' => Campaign::TYPE_WEB
        ]);

        $config = $this->createPhoneNumberConfig([
            'recording_enabled_at' => null,
            'whisper_message'      => null
        ]);

        $pool = $this->createPhoneNumberPool([
            'phone_number_config_id' => $config->id,
            'campaign_id'            => $campaign->id,
        ]);

        $phone = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id
        ], $this->createPhoneNumberConfig());
       
        $incomingCall = $this->generateIncomingCall($phone->e164Format());

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);
        $response->assertStatus(200);
        $response->assertSee(
            '<Response><Dial answerOnBridge="true" record="do-not-record"><Number>' 
            . $pool->forwardToPhoneNumber() 
            . '</Number></Dial></Response>'
        );

        //
        //  Try again with recording on
        //
        $config->recording_enabled_at = date('Y-m-d H:i:s');
        $config->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);
        $response->assertStatus(200);
        $response->assertSee('<Response><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number>' .$pool->forwardToPhoneNumber() . '</Number></Dial></Response>');
    
        //
        //  Try again with audio clip
        //
        $audioClip = factory(AudioClip::class)->create([
            'company_id' => $phone->company_id,
            'created_by' => $this->user->id,
            'path'       => AudioClip::storagePath($this->company, 'audio_clips') . '/' . mt_rand(99999, 9999999) . '.mp3'
        ]);
        $config->audio_clip_id = $audioClip->id;
        $config->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);
        $response->assertStatus(200);
        $response->assertSee(
            '<Response><Play>' 
            . $audioClip->getURL() 
            . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' 
            . route('incoming-call-recording-available') 
            . '" recordingStatusCallbackEvent="completed"><Number>' 
            . $pool->forwardToPhoneNumber() 
            . '</Number></Dial></Response>'
        );

        //
        //  Try again with whisper
        //
        $config->whisper_message  = 'Hello world';
        $config->whisper_language = 'en';
        $config->whisper_voice    = 'alice'; 
        $config->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);

        $response->assertStatus(200);
        $response->assertSee(str_replace('&', '&amp;', 
            '<Response><Play>' . $audioClip->getURL() . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number url="' . route('incoming-call-whisper',[
                'whisper_message' => $config->whisper_message,
                'whisper_language'=> $config->whisper_language,
                'whisper_voice'   => $config->whisper_voice
            ]) . '" method="GET">' . $config->forwardToPhoneNumber() . '</Number></Dial></Response>')
        );
    }

    /**
     * Test updating call status
     * 
     * @group incoming-calls
     */
    public function testIncomingCallUpdated()
    {
        $this->expectsEvents(IncomingCallUpdatedEvent::class);
        
        $campaign = $this->createCampaign([
            'type' => Campaign::TYPE_RADIO
        ]);

        $config = $this->createPhoneNumberConfig([
            'recording_enabled_at' => null,
            'whisper_message'      => null
        ]);

        $pool = $this->createPhoneNumberPool([
            'phone_number_config_id' => $config->id,
            'campaign_id'            => $campaign->id,
        ]);

        $phone = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id
        ], $this->createPhoneNumberConfig());
       
        $incomingCall = $this->generateIncomingCall($phone->e164Format());

        //  Send original call
        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);
        $response->assertStatus(200);

        //  Send updated call
        $incomingCall['CallDuration'] = mt_rand(10, 999);
        $incomingCall['CallStatus']   = 'completed';

        $response = $this->json('GET', 'http://localhost/v1/incoming-calls/status-changed', $incomingCall);
        $response->assertStatus(200);
        $call = Call::where('external_id', $incomingCall['CallSid'])->first();

        $this->assertTrue($call != null);
        $this->assertTrue($call->status == $incomingCall['CallStatus']);
        $this->assertTrue($call->duration == $incomingCall['CallDuration']);
    }

    /**
     * Test handling a recording
     * 
     * @group incoming-calls
     */
    public function testIncomingCallRecordingAvailable()
    {
        Storage::fake();

        $campaign = $this->createCampaign([
            'type' => Campaign::TYPE_RADIO
        ]);

        $config = $this->createPhoneNumberConfig([
            'recording_enabled_at' => null,
            'whisper_message'      => null
        ]);

        $pool = $this->createPhoneNumberPool([
            'phone_number_config_id' => $config->id,
            'campaign_id'            => $campaign->id,
        ]);

        $phone = $this->createPhoneNumber([
            'phone_number_pool_id' => $pool->id
        ], $this->createPhoneNumberConfig());

        $incomingCall = $this->generateIncomingCall($phone->e164Format());

        //  Send call
        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);
        $response->assertStatus(200);

        $twilioRecording = $this->generateReadyRecording($incomingCall['CallSid']);

        //  Send recording available alert
        $response = $this->json('POST', 'http://localhost/v1/incoming-calls/recording-available', $twilioRecording);

        //  Make sure the new recording exists
        $recording = CallRecording::where('external_id', $twilioRecording['RecordingSid'])->first();
        $this->assertTrue($recording != null);

        Storage::assertExists($recording->path);
    }

    /**
     * Test that webhooks are being fired and logs
     * 
     * @group incoming-calls
     */
    public function testWebhooksFiresAndLogsCall()
    {
        $user = $this->createUser();

        $companyWebhookActions = json_decode($this->company->webhook_actions, true);

        $phone = $this->createPhoneNumber();

        $incomingCall = $this->generateIncomingCall($phone->e164Format());

        //  Send original call
        $response = $this->json('GET', 'http://localhost/v1/incoming-calls', $incomingCall);
        $response->assertStatus(200);

        //  Send updated call
        $incomingCall['CallDuration'] = mt_rand(10, 999);
        $incomingCall['CallStatus']   = 'answered';
        $response = $this->json('GET', 'http://localhost/v1/incoming-calls/status-changed', $incomingCall);
        $response->assertStatus(200);

        //  Send completed call
        $incomingCall['CallDuration'] = mt_rand(10, 999);
        $incomingCall['CallStatus']   = 'completed';
        $response = $this->json('GET', 'http://localhost/v1/incoming-calls/status-changed', $incomingCall);
        $response->assertStatus(200);

        //  Make sure the webhooks were fired
        $webhookCalls = WebhookCall::where('company_id', $this->company->id)->get();
        $this->assertTrue( count($webhookCalls) === 3 );

        foreach( $webhookCalls as $webhookCall ){
            $webhookAction = $companyWebhookActions[$webhookCall->webhook_action_id];
            $this->assertTrue( $webhookCall->status_code != null );
            $this->assertTrue( $webhookCall->url ==  $webhookAction['url'] );
            $this->assertTrue( $webhookCall->method ==  $webhookAction['method'] );
        }
    }

    /**
     * Test the call whisper twiml is ok
     * 
     * @group incoming-calls
     */
    public function testCallWhisper()
    {
        $message = 'Oh, ...hello there...';
        $voice   = 'alice';
        $lang    = 'en';

        $response = $this->json('GET', route('incoming-call-whisper', [
            'whisper_message'  => $message,
            'whisper_voice'    => $voice,
            'whisper_language' => $lang,
        ]));

        $response->assertStatus(200);

        $response->assertSee('<Response><Say language="' . $lang . '" voice="' . $voice . '">' . $message . '</Say></Response>');
    }

    public function createTestingPhone($phoneFields = [], $campaignFields = [])
    {
        $number  = $this->getTestFromPhone();
        $fNumber = env('TWILIO_TESTING_FORWARD_NUMBER');

        PhoneNumber::where('number', $number)->delete();

        $campaign = $this->createCampaign($campaignFields ?: []);

        $phone = $this->createPhoneNumber(array_merge([
            'number' => $number,
        ], $phoneFields ?: []));
        
        return $phone;
    }

    public function getTestFromPhone()
    {
        return env('TWILIO_TESTING_NUMBER');
    }

    public function getTestForwardPhone()
    {
        return env('TWILIO_TESTING_FORWARD_NUMBER');
    }

    /**
     * Generate a fake incoming call
     * 
     */
    public function generateIncomingCall($toPhone, $with = [])
    {
        $faker = $this->faker();

        return array_merge([
            'CallSid'       => str_random(40),
            'CallStatus'    => 'ringing',
            'To'            => $toPhone,
            'ToCity'        => $faker->city,
            'ToCountry'     => 'US',
            'ToState'       => $faker->stateAbbr,
            'ToZip'         => $faker->postcode,
            'From'          => $faker->e164PhoneNumber,
            'FromCity'      => $faker->city,
            'FromCountry'   => 'US',
            'FromState'     => $faker->stateAbbr,
            'FromZip'       => $faker->postcode,
            'Direction'     => 'inbound'
        ], $with); 
    }

    /**
     * Generate a fake ready recording
     * 
     */
    public function generateReadyRecording($callSid, $with = [])
    {
        $faker  = $this->faker();
        $path   = 'temp/' . str_random(32) . '.mp3';

        Storage::put($path, str_random(100, 999) , 'public');

        return array_merge([
            'CallSid'               => $callSid,
            'RecordingSid'          => str_random(40),
            'RecordingStatus'       => 'completed',
            'RecordingDuration'     => mt_rand(9, 999),
            'RecordingChannels'     => mt_rand(1, 2),
            'RecordingStartTime'    => date('U'),
            'RecordingSource'       => 'DialVerb',
            'RecordingUrl'          => trim(env('AWS_URL'), '/') . '/' . trim($path, '/')
        ], $with);
    }
}
