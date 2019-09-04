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
use App\Models\Company\CampaignPhoneNumber;
use App\Models\Company\WebhookCall;
use \Tests\Models\TwilioCall;
use \Tests\Models\TwilioRecording;
use App\Events\IncomingCallEvent;
use App\Events\IncomingCallUpdatedEvent;
use App\Models\Company\PhoneNumber\Call;
use App\Models\Company\PhoneNumber\CallRecording;
use Storage;

class CallTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test handling an incoming phone call for a print campaign tied to a single phone number
     * 
     * @group incoming-calls
     */
    public function testHandleIncomingPhoneCallForPrintCampaign()
    {
        $this->expectsEvents(IncomingCallEvent::class);

        $phone = $this->createTestingPhone(null, [
            'type' => Campaign::TYPE_PRINT
        ]);

        $callData = $this->getCallData($phone);

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);
        $response->assertStatus(200);
        $response->assertSee('<Response><Dial answerOnBridge="true" record="do-not-record"><Number>' .$phone->forwardToPhoneNumber() . '</Number></Dial></Response>');

        //
        //  Try again with recording on
        //
        $phone->recording_enabled_at = date('Y-m-d H:i:s');
        $phone->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData); 
        $response->assertStatus(200);
        $response->assertSee('<Response><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number>' .$phone->forwardToPhoneNumber() . '</Number></Dial></Response>');
    
        //
        //  Try again with audio clip
        //
        $audioClip = factory(AudioClip::class)->create([
            'company_id' => $phone->company_id,
            'created_by' => $this->user->id,
            'path'       => AudioClip::storagePath($this->company, 'audio_clips') . '/' . mt_rand(99999, 9999999) . '.mp3'
        ]);
        $phone->audio_clip_id = $audioClip->id;
        $phone->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);
        $response->assertStatus(200);
        $response->assertSee('<Response><Play>' . $audioClip->getURL() . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number>' .$phone->forwardToPhoneNumber() . '</Number></Dial></Response>');
        
        //
        //  Try again with whisper
        //
        $phone->whisper_message  = 'Hello world';
        $phone->whisper_language = 'en';
        $phone->whisper_voice    = 'alice'; 
        $phone->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);
        $response->assertStatus(200);
        $response->assertSee(str_replace('&', '&amp;', 
            '<Response><Play>' . $audioClip->getURL() . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number url="' . route('incoming-call-whisper',[
                'whisper_message' => $phone->whisper_message,
                'whisper_language'=> $phone->whisper_language,
                'whisper_voice'   => $phone->whisper_voice
            ]) . '" method="GET">' . $phone->forwardToPhoneNumber() . '</Number></Dial></Response>')
        );
    }

    /**
     * Test handling an incoming phone number pool call for a print campaign 
     * 
     * @group incoming-calls
     */
    public function testHandleIncomingPhonePoolCallForPrintCampaign()
    {
        $this->expectsEvents(IncomingCallEvent::class);

        $user = $this->createUser();

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'forward_to_number' => env('TWILIO_TESTING_FORWARD_NUMBER')
        ]);

        $phone = $this->createTestingPhone([
            'phone_number_pool_id' => $pool->id
        ], [
            'type' => Campaign::TYPE_PRINT
        ]);
      
        $callData = $this->getCallData($phone);

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);
        $response->assertStatus(200);
        $response->assertSee('<Response><Dial answerOnBridge="true" record="do-not-record"><Number>' .$pool->forwardToPhoneNumber() . '</Number></Dial></Response>');

        //
        //  Try again with recording on
        //
        $pool->recording_enabled_at = date('Y-m-d H:i:s');
        $pool->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);
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
        $pool->audio_clip_id = $audioClip->id;
        $pool->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);
        $response->assertStatus(200);
        $response->assertSee('<Response><Play>' . $audioClip->getURL() . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number>' . $pool->forwardToPhoneNumber() . '</Number></Dial></Response>');

        //
        //  Try again with whisper
        //
        $pool->whisper_message  = 'Hello world';
        $pool->whisper_language = 'en';
        $pool->whisper_voice    = 'alice'; 
        $pool->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);
        $response->assertStatus(200);
        $response->assertSee(str_replace('&', '&amp;', 
            '<Response><Play>' . $audioClip->getURL() . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number url="' . route('incoming-call-whisper',[
                'whisper_message' => $pool->whisper_message,
                'whisper_language'=> $pool->whisper_language,
                'whisper_voice'   => $pool->whisper_voice
            ]) . '" method="GET">' . $pool->forwardToPhoneNumber() . '</Number></Dial></Response>')
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

        $phone = $this->createTestingPhone(null, [
            'type' => Campaign::TYPE_RADIO
        ]);

        $callData = $this->getCallData($phone);

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);

        $response->assertStatus(200);
        $response->assertSee('<Response><Dial answerOnBridge="true" record="do-not-record"><Number>' .$phone->forwardToPhoneNumber() . '</Number></Dial></Response>');


        //
        //  Try again with recording on
        //
        $phone->recording_enabled_at = date('Y-m-d H:i:s');
        $phone->save();
        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);

        $response->assertStatus(200);
        $response->assertSee('<Response><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number>' .$phone->forwardToPhoneNumber() . '</Number></Dial></Response>');
    
        //
        //  Try again with audio clip
        //
        $audioClip = factory(AudioClip::class)->create([
            'company_id' => $phone->company_id,
            'created_by' => $this->user->id,
            'path'       => AudioClip::storagePath($this->company, 'audio_clips') . '/' . mt_rand(99999, 9999999) . '.mp3'
        ]);
        $phone->audio_clip_id = $audioClip->id;
        $phone->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);

        $response->assertStatus(200);
        $response->assertSee('<Response><Play>' . $audioClip->getURL() . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number>' .$phone->forwardToPhoneNumber() . '</Number></Dial></Response>');
        
        //
        //  Try again with whisper
        //
        $phone->whisper_message  = 'Hello world';
        $phone->whisper_language = 'en';
        $phone->whisper_voice    = 'alice'; 
        $phone->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);

        $response->assertStatus(200);
        $response->assertSee(str_replace('&', '&amp;', 
            '<Response><Play>' . $audioClip->getURL() . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number url="' . route('incoming-call-whisper',[
                'whisper_message' => $phone->whisper_message,
                'whisper_language'=> $phone->whisper_language,
                'whisper_voice'   => $phone->whisper_voice
            ]) . '" method="GET">' . $phone->forwardToPhoneNumber() . '</Number></Dial></Response>')
        );
    }

    /**
     * Test handling an incoming phone number pool call for a radio campaign 
     * 
     * @group incoming-calls
     */
    public function testHandleIncomingPhonePoolCallForRadioCampaign()
    {
        $this->expectsEvents(IncomingCallEvent::class);

        $user = $this->createUser();

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'forward_to_number' => env('TWILIO_TESTING_FORWARD_NUMBER')
        ]);

        $phone = $this->createTestingPhone([
            'phone_number_pool_id' => $pool->id
        ], [
            'type' => Campaign::TYPE_RADIO
        ]);
       
        $callData = $this->getCallData($phone);

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);
        $response->assertStatus(200);
        $response->assertSee('<Response><Dial answerOnBridge="true" record="do-not-record"><Number>' .$pool->forwardToPhoneNumber() . '</Number></Dial></Response>');

        //
        //  Try again with recording on
        //
        $pool->recording_enabled_at = date('Y-m-d H:i:s');
        $pool->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);
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
        $pool->audio_clip_id = $audioClip->id;
        $pool->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);

        $response->assertStatus(200);
        $response->assertSee('<Response><Play>' . $audioClip->getURL() . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number>' . $pool->forwardToPhoneNumber() . '</Number></Dial></Response>');

        //
        //  Try again with whisper
        //
        $pool->whisper_message  = 'Hello world';
        $pool->whisper_language = 'en';
        $pool->whisper_voice    = 'alice'; 
        $pool->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);

        $response->assertStatus(200);
        $response->assertSee(str_replace('&', '&amp;', 
            '<Response><Play>' . $audioClip->getURL() . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number url="' . route('incoming-call-whisper',[
                'whisper_message' => $pool->whisper_message,
                'whisper_language'=> $pool->whisper_language,
                'whisper_voice'   => $pool->whisper_voice
            ]) . '" method="GET">' . $pool->forwardToPhoneNumber() . '</Number></Dial></Response>')
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
        
        $user = $this->createUser();

        $pool = factory(PhoneNumberPool::class)->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'forward_to_number' => env('TWILIO_TESTING_FORWARD_NUMBER')
        ]);

        $phone = $this->createTestingPhone([
            'phone_number_pool_id' => $pool->id
        ], [
            'type' => Campaign::TYPE_WEB
        ]);
       
        $callData = $this->getCallData($phone);

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);
        $response->assertStatus(200);
        $response->assertSee('<Response><Dial answerOnBridge="true" record="do-not-record"><Number>' .$pool->forwardToPhoneNumber() . '</Number></Dial></Response>');

        //
        //  Try again with recording on
        //
        $pool->recording_enabled_at = date('Y-m-d H:i:s');
        $pool->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);
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
        $pool->audio_clip_id = $audioClip->id;
        $pool->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);
        $response->assertStatus(200);
        $response->assertSee('<Response><Play>' . $audioClip->getURL() . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number>' . $pool->forwardToPhoneNumber() . '</Number></Dial></Response>');

        //
        //  Try again with whisper
        //
        $pool->whisper_message  = 'Hello world';
        $pool->whisper_language = 'en';
        $pool->whisper_voice    = 'alice'; 
        $pool->save();

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);

        $response->assertStatus(200);
        $response->assertSee(str_replace('&', '&amp;', 
            '<Response><Play>' . $audioClip->getURL() . '</Play><Dial answerOnBridge="true" record="record-from-ringing" recordingStatusCallback="' . route('incoming-call-recording-available') . '" recordingStatusCallbackEvent="completed"><Number url="' . route('incoming-call-whisper',[
                'whisper_message' => $pool->whisper_message,
                'whisper_language'=> $pool->whisper_language,
                'whisper_voice'   => $pool->whisper_voice
            ]) . '" method="GET">' . $pool->forwardToPhoneNumber() . '</Number></Dial></Response>')
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
        
        $phone = $this->createTestingPhone(null, [
            'type' => Campaign::TYPE_RADIO
        ]);

        $callData = $this->getCallData($phone);

        //  Send original call
        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);
        $response->assertStatus(200);

        //  Send updated call
        $callData['CallDuration'] = mt_rand(10, 999);
        $callData['CallStatus']   = 'completed';

        $response = $this->json('GET', 'http://localhost/v1/incoming/calls/status-changed', $callData);
        $response->assertStatus(200);
        $call = Call::where('external_id', $callData['CallSid'])->first();

        $this->assertTrue($call != null);
        $this->assertTrue($call->status == $callData['CallStatus']);
        $this->assertTrue($call->duration == $callData['CallDuration']);
    }

    /**
     * Test handling a recording
     * 
     * @group incoming-calls
     */
    public function testIncomingCallRecordingAvailable()
    {
        $user = $this->createUser();

        $phone = $this->createTestingPhone(null, [
            'type' => Campaign::TYPE_RADIO
        ]);

        $callData = $this->getCallData($phone);

        //  Send call
        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);
        $response->assertStatus(200);

        //  Upload file as recording
        $path = 'temp/audio.mp3';
        Storage::put($path, 'mp3Data....' , 'public');

        $twilioRecording = factory(TwilioRecording::class)->make([
            'CallSid'      => $callData['CallSid'],
            'RecordingUrl' => trim(env('AWS_URL'), '/') . '/' . trim($path, '/')
        ]);

        CallRecording::testing();

        //  Send recording available alert
        $response = $this->json('POST', 'http://localhost/v1/incoming/calls/recording-available', [
            'CallSid'           => $twilioRecording->CallSid,
            'RecordingSid'      => $twilioRecording->RecordingSid,
            'RecordingUrl'      => $twilioRecording->RecordingUrl,
            'RecordingDuration' => $twilioRecording->RecordingDuration
        ]);

        //  Make sure the new recording exists
        $recording = CallRecording::where('external_id', $twilioRecording->RecordingSid)->first();
        $this->assertTrue($recording != null);

        $this->assertTrue($recording->path != null);
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

        $phone = $this->createTestingPhone(null, [
            'type' => Campaign::TYPE_RADIO
        ]);

        $callData = $this->getCallData($phone);

        //  Send original call
        $response = $this->json('GET', 'http://localhost/v1/incoming/calls', $callData);
        $response->assertStatus(200);

        //  Send updated call
        $callData['CallDuration'] = mt_rand(10, 999);
        $callData['CallStatus']   = 'answered';
        $response = $this->json('GET', 'http://localhost/v1/incoming/calls/status-changed', $callData);
        $response->assertStatus(200);

        //  Send completed call
        $callData['CallDuration'] = mt_rand(10, 999);
        $callData['CallStatus']   = 'completed';
        $response = $this->json('GET', 'http://localhost/v1/incoming/calls/status-changed', $callData);
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
        $number  = env('TWILIO_TESTING_NUMBER');
        $fNumber = env('TWILIO_TESTING_FORWARD_NUMBER');

        PhoneNumber::where('number', $number)->delete();

        $phone = $this->createPhoneNumber(array_merge([
            'number'            => $number,
            'forward_to_number' => $fNumber
        ], $phoneFields ?: []));

        $campaign = $this->createCampaign($campaignFields ?: []);

        if( $phone->phone_number_pool_id ){
            $campaign->phone_number_pool_id = $phone->phone_number_pool_id;
            $campaign->save();
        }else{
            CampaignPhoneNumber::create([
                'campaign_id'     => $campaign->id,
                'phone_number_id' => $phone->id
            ]);
        }
        
        return $phone;
    }

    public function getCallData($phone)
    {
        $incomingCall = factory(TwilioCall::class)->make();

        return [
            'CallSid'       => $incomingCall->CallSid,
            'CallStatus'    => $incomingCall->CallStatus,
            'Direction'     => $incomingCall->Direction,
            'To'            => $phone->phoneNumber(),
            'From'          => $incomingCall->From,
            'ToCity'        => $incomingCall->ToCity,
            'ToCountry'     => $incomingCall->ToCountry,
            'ToState'       => $incomingCall->ToState,
            'ToZip'         => $incomingCall->ToZip,
            'FromCity'      => $incomingCall->FromCity,
            'FromCountry'   => $incomingCall->FromCountry,
            'FromState'     => $incomingCall->FromState,
            'FromZip'       => $incomingCall->FromZip,
        ];
    }
}
