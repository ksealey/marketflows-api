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
use \App\Models\Plugin;
use App\Services\TranscribeService;
use Twilio\TwiML\VoiceResponse;
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
            'keypress_enabled'       => 0,
            'keypress_conversion_enabled' => 0,
            'keypress_qualification_enabled' => 0,
            'whisper_enabled'        => 0,
            'recording_enabled'      => 0,
            'transcription_enabled'  => 0,
        ]);

        $phoneNumber = $this->createPhoneNumber($company, $config);

        $faker = $this->faker();

        $to = $phoneNumber->e164Format();
        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $to,
        ]);
        $variables                 = $config->variables();
        $variables = array_merge($variables, json_decode($incomingCall->variables, true));
        $variables['company_name'] = $company->name;
        
        $variables['source']              = $phoneNumber->source;
        $variables['medium']              = $phoneNumber->medium;
        $variables['content']             = $phoneNumber->content;
        $variables['campaign']            = $phoneNumber->campaign;
        $variables['keyword']             = null;

        $variables['dialed_cc']           = PhoneNumber::countryCode($to);
        $variables['dialed_number']       = PhoneNumber::number($to);
        
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
        $response->assertHeader('Content-Type', 'application/xml');
        $callEndUrl    = htmlspecialchars(route('dialed-call-ended'));
        $preconnectUrl = htmlspecialchars(route('dialed-call-preconnect', [
            'phone_number_config_id' => $config->id,
            'variables'              => json_encode($variables)
        ]));

        $response->assertSee(
            '<Response><Dial answerOnBridge="true" action="' . $callEndUrl . '" hangupOnStar="true">'
            . '<Number url="' .  $preconnectUrl . '" method="GET">' . $config->forwardToPhoneNumber() . '</Number></Dial></Response>'
        , false);
        $response->assertStatus(200);

        $contact = Contact::where('country_code', PhoneNumber::countryCode($incomingCall->From))
                            ->where('number', PhoneNumber::number($incomingCall->From))
                            ->first();

        $this->assertDatabaseHas('calls', [
            'account_id'                    => $this->account->id,
            'company_id'                    => $company->id,
            'contact_id'                    => $contact->id,
            'keyword_tracking_pool_id'      => null,
            'keyword_tracking_pool_name'    => null,
            'phone_number_id'               => $phoneNumber->id,
            'phone_number_name'             => $phoneNumber->name,
            'type'                          => $phoneNumber->type,
            'category'                      => $phoneNumber->category,
            'sub_category'                  => $phoneNumber->sub_category,
            'external_id'                   => $incomingCall->CallSid,
            'direction'                     => 'Inbound',
            'status'                        => 'Ringing',
            'source'                        => $phoneNumber->source,
            'medium'                        => $phoneNumber->medium,
            'content'                       => $phoneNumber->content,
            'campaign'                      => $phoneNumber->campaign,
            'keyword'                       => null,
            'is_paid'                       => $phoneNumber->is_paid,
            'is_organic'                    => $phoneNumber->is_organic, 
            'is_direct'                     => $phoneNumber->is_direct,
            'is_referral'                   => $phoneNumber->is_referral,
            'is_remarketing'                => $phoneNumber->is_remarketing,
            'is_search'                     => $phoneNumber->is_search,
            'recording_enabled'             => $config->recording_enabled,
            'forwarded_to'                  => $config->forwardToPhoneNumber(),
            'duration'                      => null,
        ]);

        $dialSid = str_random(40);

        //  Pre-connect webhook
        $response = $this->json('GET', $preconnectUrl, [
            'AccountSid'    => $incomingCall->AccountSid,
            'ParentCallSid' => $incomingCall->CallSid,
            'CallSid'       => $dialSid,
        ]);
        $acceptCallUrl = htmlspecialchars(route('dialed-call-collect-accept', [
            'ParentCallSid'          => $incomingCall->CallSid,
            'failed_attempts'        => 0,
            'tts_language'           => $company->tts_language,
            'tts_voice'              => $company->tts_voice,
            'phone_number_config_id' => $config->id,
        ]));
        
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee(
            '<Response>' .
                '<Gather numDigits="1" timeout="10" actionOnEmptyResult="true" method="POST" action="' . $acceptCallUrl . '">' . 
                    '<Say language="' . $company->tts_language . '" voice="Polly.' . $company->tts_voice . '">To accept this call press 1. To reject, press 2.</Say>' .
                '</Gather>' . 
            '</Response>', 
        false);
        $response->assertStatus(200);

        // No selection (timeout)
        $response = $this->json('POST', $acceptCallUrl, [
            'AccountSid'    => $incomingCall->AccountSid,
            'ParentCallSid' => $incomingCall->CallSid,
            'CallSid'       => $dialSid,
            'Digits'        => ''
        ]);
        $acceptCallUrl = htmlspecialchars(route('dialed-call-collect-accept', [
            'ParentCallSid'          => $incomingCall->CallSid,
            'failed_attempts'        => 1,
            'tts_language'           => $company->tts_language,
            'tts_voice'              => $company->tts_voice,
            'phone_number_config_id' => $config->id
        ]));

        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee(
            '<Response>' .
                '<Gather numDigits="1" timeout="10" actionOnEmptyResult="true" method="POST" action="' . $acceptCallUrl . '">' . 
                    '<Say language="' . $company->tts_language . '" voice="Polly.' . $company->tts_voice . '">To accept this call press 1. To reject, press 2.</Say>' . 
                '</Gather>' . 
            '</Response>', 
        false);

        $this->assertDatabaseHas('calls', [
            'external_id' => $incomingCall->CallSid,
            'status'      => 'Ringing',
        ]);

        // Accept Call
        $conferenceEndUrl = route('incoming-call-conference-ended', [ 
            'phone_number_config_id' => $config->id,
            'variables'              => json_encode($variables),
            'ParentCallSid'          => $incomingCall->CallSid,
        ]);
        $twiml = new VoiceResponse();
        $dial  = $twiml->dial('', [
            'hangupOnStar' => true,
            'action'       => $conferenceEndUrl,
            'method'       => 'POST'
        ]);

        $dial->conference($incomingCall->CallSid, [
            'startConferenceOnEnter' => true,
            'endConferenceOnExit'    => true,
            'beep'                   => false,
            'participantLabel'       => 'Agent',
            'waitUrl'                => '',
        ]);

        $this->mock(TwilioClient::class, function($mock) use($dialSid, $twiml){
            $mock->shouldReceive('calls')
                 ->with($dialSid)
                 ->once()
                 ->andReturn($mock);

            $mock->shouldReceive('update')
                 ->once();
        });

        $response = $this->json('POST', $acceptCallUrl, [
            'CallSid'       => $dialSid,
            'AccountSid'    => $incomingCall->AccountSid,
            'ParentCallSid' => $incomingCall->CallSid,
            'Digits'        => 1,
            'variables'     => json_encode($variables)
        ]);
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee('<Response/>', false);

        $this->assertDatabaseHas('calls', [
            'external_id' => $incomingCall->CallSid,
            'status'      => 'In Progress',
        ]);

        //  Move caller to conference
        $response = $this->json('POST', $callEndUrl, [
            'CallSid'       => $incomingCall->CallSid,
            'AccountSid'    => $incomingCall->AccountSid,
            'ParentCallSid' => $incomingCall->CallSid,
        ]);
        $response->assertSee('<Response><Dial hangupOnStar="true"><Conference beep="false" participantLabel="Caller" endConferenceOnExit="true" startConferenceOnEnter="true" waitUrl="">' . $incomingCall->CallSid . '</Conference></Dial></Response>', false);
        
        Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
            return $company->id === $event->call->company_id && $event->name === Plugin::EVENT_CALL_START;
        });
        Event::assertNotDispatched(CallEvent::class, function(CallEvent $event) use($company){
            return $event->name === Plugin::EVENT_CALL_END;
        });
        Event::assertDispatched(CallEvent::class, 1);

        //  
        //  End Conference
        //
        $callDuration = mt_rand(1, 1000);
        $response = $this->json('POST', route('incoming-call-cleanup'), [
            'CallDuration'  => $callDuration,
            'CallSid'       => $incomingCall->CallSid,
            'AccountSid'    => $incomingCall->AccountSid,
            'ParentCallSid' => $incomingCall->CallSid,
        ]);
        
        $this->assertDatabaseHas('calls', [
            'external_id' => $incomingCall->CallSid,
            'status'      => 'In Progress',
            'duration'    => $callDuration
        ]);
        
        $response = $this->json('POST', $conferenceEndUrl, [
            'CallSid'       => $dialSid,
            'AccountSid'    => $incomingCall->AccountSid,
            'ParentCallSid' => $incomingCall->CallSid,
        ]);
        
        $this->assertDatabaseHas('calls', [
            'external_id' => $incomingCall->CallSid,
            'status'      => 'Completed',
            'duration'    => $callDuration
        ]);

        Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
            return $company->id === $event->call->company_id && $event->name === Plugin::EVENT_CALL_END;
        });

        Event::assertDispatched(CallEvent::class, 2);
    }

    /**
     * Test handling an incoming call with all options
     * 
     * @group incoming-calls
     */
    public function testValidIncomingCallWithAllOptions()
    {
        Event::fake();

        $company     = $this->createCompany();
        $config      = $this->createConfig($company, [
            'greeting_enabled'                  => 1,
            'greeting_message'                  => '${Caller_First_Name} ${Caller_Last_Name} ${Caller_City} ${Caller_State} ${Caller_Zip} ${Caller_Country} ${Caller_Number} ${Caller_CC} ${Dialed_CC} ${Dialed_Number} ${Forward_Number} ${Company_Name}',
            'keypress_enabled'                  => 1,
            'keypress_directions_message'       => '${Caller_First_Name} ${Caller_Last_Name} ${Caller_City} ${Caller_State} ${Caller_Zip} ${Caller_Country} ${Caller_Number} ${Caller_CC} ${Dialed_CC} ${Dialed_Number} ${Forward_Number} ${Company_Name} ${Keypress_Key} ${Failed_Attempts} ${Remaining_Attempts}',
            'keypress_error_message'            => 'E${Caller_First_Name} ${Caller_Last_Name} ${Caller_City} ${Caller_State} ${Caller_Zip} ${Caller_Country} ${Caller_Number} ${Caller_CC} ${Dialed_CC} ${Dialed_Number} ${Forward_Number} ${Company_Name} ${Keypress_Key} ${Failed_Attempts} ${Remaining_Attempts}',
            'keypress_success_message'          => 'S${Caller_First_Name} ${Caller_Last_Name} ${Caller_City} ${Caller_State} ${Caller_Zip} ${Caller_Country} ${Caller_Number} ${Caller_CC} ${Dialed_CC} ${Dialed_Number} ${Forward_Number} ${Company_Name} ${Keypress_Key} ${Failed_Attempts} ${Remaining_Attempts}',
            'keypress_conversion_enabled'       => 1,
            'keypress_conversion_directions_message'    => '${Caller_State} ${Caller_Zip} ${Caller_Country} ${Caller_Number} ${Dialed_Number} ${Forward_Number} ${Company_Name} ${Source} ${Medium} ${Campaign} ${Content} ${Keyword} ${Converted_Key} ${Unconverted_Key} ${Failed_Attempts} ${Remaining_Attempts}',
            'keypress_conversion_error_message'         => 'E${Caller_State} ${Caller_Zip} ${Caller_Country} ${Caller_Number} ${Dialed_Number} ${Forward_Number} ${Company_Name} ${Source} ${Medium} ${Campaign} ${Content} ${Keyword} ${Converted_Key} ${Unconverted_Key} ${Failed_Attempts} ${Remaining_Attempts}',
            'keypress_conversion_success_message'       => 'S${Caller_State} ${Caller_Zip} ${Caller_Country} ${Caller_Number} ${Dialed_Number} ${Forward_Number} ${Company_Name} ${Source} ${Medium} ${Campaign} ${Content} ${Keyword} ${Converted_Key} ${Unconverted_Key} ${Failed_Attempts} ${Remaining_Attempts}',
            
            'keypress_qualification_enabled'               => 1,
            'keypress_qualification_directions_message'    => 'Q${Caller_State} ${Caller_Zip} ${Caller_Country} ${Caller_Number} ${Dialed_Number} ${Forward_Number} ${Company_Name} ${Source} ${Medium} ${Campaign} ${Content} ${Keyword} ${Converted_Key} ${Unconverted_Key} ${Failed_Attempts} ${Remaining_Attempts}',
            'keypress_qualification_error_message'         => 'EQ${Caller_State} ${Caller_Zip} ${Caller_Country} ${Caller_Number} ${Dialed_Number} ${Forward_Number} ${Company_Name} ${Source} ${Medium} ${Campaign} ${Content} ${Keyword} ${Converted_Key} ${Unconverted_Key} ${Failed_Attempts} ${Remaining_Attempts}',
            'keypress_qualification_success_message'       => 'SQ${Caller_State} ${Caller_Zip} ${Caller_Country} ${Caller_Number} ${Dialed_Number} ${Forward_Number} ${Company_Name} ${Source} ${Medium} ${Campaign} ${Content} ${Keyword} ${Converted_Key} ${Unconverted_Key} ${Failed_Attempts} ${Remaining_Attempts}',
            

            'whisper_enabled'                   => 1,
            'whisper_message'                   => '${Caller_First_Name} ${Caller_Last_Name} ${Caller_City} ${Caller_State} ${Caller_Zip} ${Caller_Country} ${Caller_Number} ${Caller_CC} ${Dialed_CC} ${Dialed_Number} ${Forward_Number} ${Company_Name} ${Source} ${Medium} ${Campaign} ${Content} ${Keyword}',
            'recording_enabled'                 => 1,
            'transcription_enabled'             => 1,
        ]);

        $phoneNumber = $this->createPhoneNumber($company, $config);

        $faker = $this->faker();

        $to = $phoneNumber->e164Format();
        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $to,
        ]);
        $variables                 = $config->variables();
        $variables = array_merge($variables, json_decode($incomingCall->variables, true));
        $variables['company_name'] = $company->name;
        
        $variables['dialed_cc']           = PhoneNumber::countryCode($to);
        $variables['dialed_number']       = PhoneNumber::number($to);
        
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
        $response->assertHeader('Content-Type', 'application/xml');
        $collectUrl = route('incoming-call-collect', [
            'company_id'             => $company->id,
            'phone_number_id'        => $phoneNumber->id,
            'phone_number_config_id' => $config->id,
            'failed_attempts'        => 0,
            'variables'              => json_encode($variables)
        ]);

        $response->assertSee(
            '<Response>' . 
                '<Say language="' . $company->tts_language. '" voice="Polly.' . $company->tts_voice . '">'. 
                    $variables['caller_first_name'] . ' ' . $variables['caller_last_name'] . ' ' . $variables['caller_city'] . ' ' . $variables['caller_state'] . ' ' . $variables['caller_zip'] . ' ' . $variables['caller_country'] . ' ' . $variables['caller_number'] . ' ' .  $variables['caller_cc'] . ' ' . $variables['dialed_cc'] . ' ' . $variables['dialed_number'] . ' ' . $variables['forward_number'] . ' ' . $variables['company_name'] . 
                '</Say>' . 
               '<Gather numDigits="1" timeout="' . $config->keypress_timeout . '" actionOnEmptyResult="true" method="POST" action="' . 
                htmlspecialchars($collectUrl) . '">' .
                    '<Say language="' . $company->tts_language. '" voice="Polly.' . $company->tts_voice . '">' . 
                        $variables['caller_first_name'] . ' ' . $variables['caller_last_name'] . ' ' . $variables['caller_city'] . ' ' . $variables['caller_state'] . ' ' . $variables['caller_zip'] . ' ' . $variables['caller_country'] . ' ' . $variables['caller_number'] . ' ' .  $variables['caller_cc'] . ' ' . $variables['dialed_cc'] . ' ' . $variables['dialed_number'] . ' ' . $variables['forward_number'] . ' ' . $variables['company_name'] . ' ' . $variables['keypress_key'] . ' '  . $variables['failed_attempts'] . ' ' . $variables['remaining_attempts'] .
                    '</Say></Gather></Response>'
        , false);
        $response->assertStatus(200);

        //
        //  Pass in failed digits
        //
        $response = $this->json('POST', $collectUrl, [
            'AccountSid' => $incomingCall->AccountSid,
            'Digits' => 5
        ]);
        $response->assertHeader('Content-Type', 'application/xml');
        
        $variables['failed_attempts']    = 1;
        $variables['remaining_attempts'] = 2;
        $collectUrl = route('incoming-call-collect', [
            'company_id'             => $company->id,
            'phone_number_id'        => $phoneNumber->id,
            'phone_number_config_id' => $config->id,
            'failed_attempts'        => 1,
            'variables'              => json_encode($variables)
        ]);
        
        $response->assertSee(
            '<Response>' .
               '<Gather numDigits="1" timeout="' . $config->keypress_timeout . '" actionOnEmptyResult="true" method="POST" action="' . 
                htmlspecialchars($collectUrl) . '">' .
                    '<Say language="' . $company->tts_language. '" voice="Polly.' . $company->tts_voice . '">' . 
                        'E' .$variables['caller_first_name'] . ' ' . $variables['caller_last_name'] . ' ' . $variables['caller_city'] . ' ' . $variables['caller_state'] . ' ' . $variables['caller_zip'] . ' ' . $variables['caller_country'] . ' ' . $variables['caller_number'] . ' ' .  $variables['caller_cc'] . ' ' . $variables['dialed_cc'] . ' ' . $variables['dialed_number'] . ' ' . $variables['forward_number'] . ' ' . $variables['company_name'] . ' ' . $variables['keypress_key'] . ' '  . $variables['failed_attempts'] . ' ' . $variables['remaining_attempts'] .
                    '</Say></Gather></Response>'
        , false);
        $response->assertStatus(200);

        //
        //  Pass in valid digits
        //
        $callEndUrl    = htmlspecialchars(route('dialed-call-ended'));

        $variables['source']              = $phoneNumber->source;
        $variables['medium']              = $phoneNumber->medium;
        $variables['content']             = $phoneNumber->content;
        $variables['campaign']            = $phoneNumber->campaign;
        $variables['keyword']             = null;
        $preconnectUrl = htmlspecialchars(route('dialed-call-preconnect', [
            'phone_number_config_id' => $config->id,
            'variables'              => json_encode($variables)
        ]));

        $incomingCall->variables = json_encode($variables);
        $response = $this->json('POST', $collectUrl, array_merge([
            'Digits'     => $config->keypress_key
        ], $incomingCall->toArray()));

        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee(
            '<Response>' .
                '<Say language="' . $company->tts_language. '" voice="Polly.' . $company->tts_voice . '">' . 
                    'S' .$variables['caller_first_name'] . ' ' . $variables['caller_last_name'] . ' ' . $variables['caller_city'] . ' ' . $variables['caller_state'] . ' ' . $variables['caller_zip'] . ' ' . $variables['caller_country'] . ' ' . $variables['caller_number'] . ' ' .  $variables['caller_cc'] . ' ' . $variables['dialed_cc'] . ' ' . $variables['dialed_number'] . ' ' . $variables['forward_number'] . ' ' . $variables['company_name'] . ' ' . $variables['keypress_key'] . ' '  . $variables['failed_attempts'] . ' ' . $variables['remaining_attempts'] .
                '</Say>' .
                '<Dial answerOnBridge="true" action="' . $callEndUrl . '" hangupOnStar="true">' .
                    '<Number url="' .  $preconnectUrl . '" method="GET">' . $config->forwardToPhoneNumber() . '</Number>' .
                '</Dial>' . 
            '</Response>'
        , false);
        
    
        $contact = Contact::where('country_code', PhoneNumber::countryCode($incomingCall->From))
                            ->where('number', PhoneNumber::number($incomingCall->From))
                            ->first();

        $this->assertDatabaseHas('calls', [
            'account_id'                    => $this->account->id,
            'company_id'                    => $company->id,
            'contact_id'                    => $contact->id,
            'keyword_tracking_pool_id'      => null,
            'keyword_tracking_pool_name'    => null,
            'phone_number_id'               => $phoneNumber->id,
            'phone_number_name'             => $phoneNumber->name,
            'type'                          => $phoneNumber->type,
            'category'                      => $phoneNumber->category,
            'sub_category'                  => $phoneNumber->sub_category,
            'external_id'                   => $incomingCall->CallSid,
            'direction'                     => 'Inbound',
            'status'                        => 'Ringing',
            'source'                        => $phoneNumber->source,
            'medium'                        => $phoneNumber->medium,
            'content'                       => $phoneNumber->content,
            'campaign'                      => $phoneNumber->campaign,
            'keyword'                       => null,
            'is_paid'                       => $phoneNumber->is_paid,
            'is_organic'                    => $phoneNumber->is_organic, 
            'is_direct'                     => $phoneNumber->is_direct,
            'is_referral'                   => $phoneNumber->is_referral,
            'is_remarketing'                => $phoneNumber->is_remarketing,
            'is_search'                     => $phoneNumber->is_search,
            'recording_enabled'             => $config->recording_enabled,
            'forwarded_to'                  => $config->forwardToPhoneNumber(),
            'duration'                      => null,
        ]);

        $dialSid = str_random(40);

        //  Pre-connect webhook
        $response = $this->json('GET', $preconnectUrl, [
            'AccountSid'    => $incomingCall->AccountSid,
            'ParentCallSid' => $incomingCall->CallSid,
            'CallSid'       => $dialSid,
        ]);
        $acceptCallUrl = htmlspecialchars(route('dialed-call-collect-accept', [
            'ParentCallSid'          => $incomingCall->CallSid,
            'failed_attempts'        => 0,
            'tts_language'           => $company->tts_language,
            'tts_voice'              => $company->tts_voice,
            'phone_number_config_id' => $config->id,
        ]));
        
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee(
            '<Response>' .
                '<Gather numDigits="1" timeout="10" actionOnEmptyResult="true" method="POST" action="' . $acceptCallUrl . '">' . 
                    '<Say language="' . $company->tts_language . '" voice="Polly.' . $company->tts_voice . '">' .
                        rtrim(trim($variables['caller_first_name'] . ' ' . $variables['caller_last_name'] . ' ' . $variables['caller_city'] . ' ' . $variables['caller_state'] . ' ' . $variables['caller_zip'] . ' ' . $variables['caller_country'] . ' ' . $variables['caller_number'] . ' ' .  $variables['caller_cc'] . ' ' . $variables['dialed_cc'] . ' ' . $variables['dialed_number'] . ' ' . $variables['forward_number'] . ' ' . $variables['company_name'] . ' ' . $variables['source'] . ' '  . $variables['medium'] . ' ' .  $variables['campaign'] . ' ' . $variables['content'] . ' ' . $variables['keyword']), '.') .
                    '. To accept this call press 1. To reject, press 2.</Say>' .
                '</Gather>' . 
            '</Response>', 
        false);
        $response->assertStatus(200);

        // No selection (timeout)
        $response = $this->json('POST', $acceptCallUrl, [
            'AccountSid'    => $incomingCall->AccountSid,
            'ParentCallSid' => $incomingCall->CallSid,
            'CallSid'       => $dialSid,
            'Digits'        => ''
        ]);
        $acceptCallUrl = htmlspecialchars(route('dialed-call-collect-accept', [
            'ParentCallSid'          => $incomingCall->CallSid,
            'failed_attempts'        => 1,
            'tts_language'           => $company->tts_language,
            'tts_voice'              => $company->tts_voice,
            'phone_number_config_id' => $config->id
        ]));

        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee(
            '<Response>' .
                '<Gather numDigits="1" timeout="10" actionOnEmptyResult="true" method="POST" action="' . $acceptCallUrl . '">' . 
                    '<Say language="' . $company->tts_language . '" voice="Polly.' . $company->tts_voice . '">To accept this call press 1. To reject, press 2.</Say>' . 
                '</Gather>' . 
            '</Response>', 
        false);

        $this->assertDatabaseHas('calls', [
            'external_id' => $incomingCall->CallSid,
            'status'      => 'Ringing',
        ]);

        // Accept Call
        $conferenceEndUrl = route('incoming-call-conference-ended', [ 
            'phone_number_config_id' => $config->id,
            'variables'              => json_encode($variables),
            'ParentCallSid'          => $incomingCall->CallSid,
        ]);
        $twiml = new VoiceResponse();
        $dial  = $twiml->dial('', [
            'hangupOnStar' => true,
            'action'       => $conferenceEndUrl,
            'method'       => 'POST'
        ]);

        $dial->conference($incomingCall->CallSid, [
            'startConferenceOnEnter' => true,
            'endConferenceOnExit'    => true,
            'beep'                   => false,
            'participantLabel'       => 'Agent',
            'waitUrl'                => '',
        ]);

        $this->mock(TwilioClient::class, function($mock) use($dialSid, $twiml){
            $mock->shouldReceive('calls')
                 ->with($dialSid)
                 ->once()
                 ->andReturn($mock);

            $mock->shouldReceive('update')
                 ->once();
        });

        $response = $this->json('POST', $acceptCallUrl, [
            'CallSid'       => $dialSid,
            'AccountSid'    => $incomingCall->AccountSid,
            'ParentCallSid' => $incomingCall->CallSid,
            'Digits'        => 1,
            'variables'     => json_encode($variables)
        ]);
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee('<Response/>', false);

        $this->assertDatabaseHas('calls', [
            'external_id' => $incomingCall->CallSid,
            'status'      => 'In Progress',
        ]);

        //  Move caller to conference
        $response = $this->json('POST', $callEndUrl, [
            'CallSid'       => $incomingCall->CallSid,
            'AccountSid'    => $incomingCall->AccountSid,
            'ParentCallSid' => $incomingCall->CallSid,
        ]);
    
        $response->assertSee('<Response><Dial hangupOnStar="true"><Conference beep="false" participantLabel="Caller" endConferenceOnExit="true" startConferenceOnEnter="true" waitUrl="">' . $incomingCall->CallSid . '</Conference></Dial></Response>', false);
        
        Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
            return $company->id === $event->call->company_id && $event->name === Plugin::EVENT_CALL_START;
        });
        Event::assertNotDispatched(CallEvent::class, function(CallEvent $event) use($company){
            return $event->name === Plugin::EVENT_CALL_END;
        });
        Event::assertDispatched(CallEvent::class, 1);

        //  
        //  End Conference for caller
        //
        $callDuration = mt_rand(1, 1000);
        $response = $this->json('POST', route('incoming-call-cleanup'), [
            'CallDuration'  => $callDuration,
            'CallSid'       => $incomingCall->CallSid,
            'AccountSid'    => $incomingCall->AccountSid,
            'ParentCallSid' => $incomingCall->CallSid,
        ]);
        
        $this->assertDatabaseHas('calls', [
            'external_id' => $incomingCall->CallSid,
            'status'      => 'In Progress',
            'duration'    => $callDuration
        ]);
        
        $response = $this->json('POST', $conferenceEndUrl, [
            'CallSid'       => $dialSid,
            'AccountSid'    => $incomingCall->AccountSid,
            'ParentCallSid' => $incomingCall->CallSid,
        ]);
        
        $this->assertDatabaseHas('calls', [
            'external_id' => $incomingCall->CallSid,
            'status'      => 'Completed',
            'duration'    => $callDuration
        ]);

        Event::assertNotDispatched(CallEvent::class, function(CallEvent $event) use($company){
            return $event->name === Plugin::EVENT_CALL_END;
        });

        //
        //  Collect conversion. Invalid, then valid.
        //
        $variables['failed_attempts']    = 0;
        $variables['remaining_attempts'] = $config->keypress_conversion_attempts;

        $conversionKeypressUrl = route('incoming-call-collect-conversion', [
            'phone_number_config_id' => $config->id,
            'failed_attempts'        => 0,
            'variables'              => json_encode($variables),
            'ParentCallSid'          => $incomingCall->CallSid
        ]);

        $response = $this->json('POST', $conversionKeypressUrl, [
            'AccountSid' => $incomingCall->AccountSid,
            'Digits'     => 9
        ]);

        $variables['failed_attempts']    = 1;
        $variables['remaining_attempts'] = $config->keypress_conversion_attempts - 1;
        $conversionKeypressUrl = route('incoming-call-collect-conversion', [
            'phone_number_config_id' => $config->id,
            'failed_attempts'        => 1,
            'variables'              => json_encode($variables),
            'ParentCallSid'          => $incomingCall->CallSid
        ]);
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertStatus(200);
        $response->assertSee(
            '<Response>' . 
                '<Gather numDigits="1" timeout="' . $config->keypress_conversion_timeout . '" actionOnEmptyResult="true" method="POST" action="' . htmlspecialchars($conversionKeypressUrl) . '">' .
                    '<Say language="en-US" voice="Polly.Joanna">' . 
                        'E' . $variables['caller_state'] . ' ' . $variables['caller_zip'] . ' ' . $variables['caller_country'] . ' ' . $variables['caller_number'] . ' ' . $variables['dialed_number'] . ' ' . $variables['forward_number'] . ' ' . $variables['company_name'] . ' ' . $variables['source'] . ' ' . $variables['medium'] . ' ' . $variables['campaign'] . ' ' . $variables['content'] . ' ' . $variables['keyword'] . ' ' . $variables['converted_key'] . ' ' . $variables['unconverted_key'] . ' ' . $variables['failed_attempts'] . ' ' . $variables['remaining_attempts'] .
                    '</Say>' . 
                '</Gather>' . 
            '</Response>', false);
        
        $response = $this->json('POST', $conversionKeypressUrl, [
            'AccountSid' => $incomingCall->AccountSid,
            'Digits'     => $config->keypress_conversion_key_converted
        ]);

        $failedAttempts    = $variables['failed_attempts'];
        $remainingAttempts = $variables['remaining_attempts'];

        $variables['failed_attempts']    = 0;
        $variables['remaining_attempts'] = $config->keypress_qualification_attempts;
        $qualificationKeypressUrl = route('incoming-call-collect-qualification', [
            'phone_number_config_id' => $config->id,
            'failed_attempts'        => 0,
            'variables'              => json_encode($variables),
            'ParentCallSid'          => $incomingCall->CallSid
        ]);

        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertStatus(200);
        $response->assertSee(
            '<Response>' . 
                '<Say language="' . $company->tts_language . '" voice="Polly.' .$company->tts_voice . '">' .
                    'S' . $variables['caller_state'] . ' ' . $variables['caller_zip'] . ' ' . $variables['caller_country'] . ' ' . $variables['caller_number'] . ' ' . $variables['dialed_number'] . ' ' . $variables['forward_number'] . ' ' . $variables['company_name'] . ' ' . $variables['source'] . ' ' . $variables['medium'] . ' ' . $variables['campaign'] . ' ' . $variables['content'] . ' ' . $variables['keyword'] . ' ' . $variables['converted_key'] . ' ' . $variables['unconverted_key'] . ' ' . $failedAttempts . ' ' . $remainingAttempts .
                '</Say>' .
                '<Gather numDigits="1" timeout="' . $config->keypress_qualification_timeout . '" actionOnEmptyResult="true" method="POST" action="' . htmlspecialchars($qualificationKeypressUrl) . '">' .
                    '<Say language="en-US" voice="Polly.Joanna">' . 
                        'Q' . $variables['caller_state'] . ' ' . $variables['caller_zip'] . ' ' . $variables['caller_country'] . ' ' . $variables['caller_number'] . ' ' . $variables['dialed_number'] . ' ' . $variables['forward_number'] . ' ' . $variables['company_name'] . ' ' . $variables['source'] . ' ' . $variables['medium'] . ' ' . $variables['campaign'] . ' ' . $variables['content'] . ' ' . $variables['keyword'] . ' ' . $variables['converted_key'] . ' ' . $variables['unconverted_key'] . ' ' . $variables['failed_attempts'] . ' ' . $variables['remaining_attempts'] .
                    '</Say>' . 
                '</Gather>' . 
            '</Response>', false);

        //
        //  Collect qualification. Invalid, then valid.
        //
        $variables['failed_attempts']    = 0;
        $variables['remaining_attempts'] = $config->keypress_qualification_attempts;

        $response = $this->json('POST', $qualificationKeypressUrl, [
            'AccountSid' => $incomingCall->AccountSid,
            'Digits'     => ''
        ]);

        $variables['failed_attempts']    = 1;
        $variables['remaining_attempts'] = $config->keypress_qualification_attempts - 1;
        $qualificationKeypressUrl = route('incoming-call-collect-qualification', [
            'phone_number_config_id' => $config->id,
            'failed_attempts'        => 1,
            'variables'              => json_encode($variables),
            'ParentCallSid'          => $incomingCall->CallSid
        ]);
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertStatus(200);
        $response->assertSee(
            '<Response>' . 
                '<Gather numDigits="1" timeout="' . $config->keypress_qualification_timeout . '" actionOnEmptyResult="true" method="POST" action="' . htmlspecialchars($qualificationKeypressUrl) . '">' .
                    '<Say language="en-US" voice="Polly.Joanna">' . 
                        'EQ' . $variables['caller_state'] . ' ' . $variables['caller_zip'] . ' ' . $variables['caller_country'] . ' ' . $variables['caller_number'] . ' ' . $variables['dialed_number'] . ' ' . $variables['forward_number'] . ' ' . $variables['company_name'] . ' ' . $variables['source'] . ' ' . $variables['medium'] . ' ' . $variables['campaign'] . ' ' . $variables['content'] . ' ' . $variables['keyword'] . ' ' . $variables['converted_key'] . ' ' . $variables['unconverted_key'] . ' ' . $variables['failed_attempts'] . ' ' . $variables['remaining_attempts'] .
                    '</Say>' . 
                '</Gather>' . 
            '</Response>', false);
        
        $response = $this->json('POST', $qualificationKeypressUrl, [
            'AccountSid' => $incomingCall->AccountSid,
            'Digits'     => $config->keypress_qualification_key_qualified
        ]);

        $failedAttempts    = $variables['failed_attempts'];
        $remainingAttempts = $variables['remaining_attempts'];

        $variables['failed_attempts']    = 0;
        $variables['remaining_attempts'] = $config->keypress_qualification_attempts;
        $qualificationKeypressUrl = route('incoming-call-collect-qualification', [
            'phone_number_config_id' => $config->id,
            'failed_attempts'        => 0,
            'variables'              => json_encode($variables),
            'ParentCallSid'          => $incomingCall->CallSid
        ]);

        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertStatus(200);
        $response->assertSee(
            '<Response>' . 
                '<Say language="' . $company->tts_language . '" voice="Polly.' .$company->tts_voice . '">' .
                    'SQ' . $variables['caller_state'] . ' ' . $variables['caller_zip'] . ' ' . $variables['caller_country'] . ' ' . $variables['caller_number'] . ' ' . $variables['dialed_number'] . ' ' . $variables['forward_number'] . ' ' . $variables['company_name'] . ' ' . $variables['source'] . ' ' . $variables['medium'] . ' ' . $variables['campaign'] . ' ' . $variables['content'] . ' ' . $variables['keyword'] . ' ' . $variables['converted_key'] . ' ' . $variables['unconverted_key'] . ' ' . $failedAttempts . ' ' . $remainingAttempts .
                '</Say>' .
            '</Response>', false);

            
        Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
            return $event->name === Plugin::EVENT_CALL_END;
        });

        Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
            return $company->id === $event->call->company_id && $event->name === Plugin::EVENT_CALL_END;
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

        $variables                 = $config->variables();
        $variables = array_merge($variables, json_decode($incomingCall->variables, true));
        $variables['company_name'] = $company->name;
        
        $variables['source']              = $phoneNumber->source;
        $variables['medium']              = $phoneNumber->medium;
        $variables['content']             = $phoneNumber->content;
        $variables['campaign']            = $phoneNumber->campaign;
        $variables['keyword']             = null;

        $variables['dialed_cc']           = PhoneNumber::countryCode($incomingCall->To);
        $variables['dialed_number']       = PhoneNumber::number($incomingCall->To);
        $variables['caller_cc']           = PhoneNumber::countryCode($incomingCall->From);
        $variables['caller_number']       = PhoneNumber::number($incomingCall->From);

        $callEndUrl    = htmlspecialchars(route('dialed-call-ended'));
        $preconnectUrl = htmlspecialchars(route('dialed-call-preconnect', [
            'phone_number_config_id' => $config->id,
            'variables'              => json_encode($variables)
        ]));

        $response->assertSee(
            '<Response>' .
                '<Play>' . $audioClip->url . '</Play>' .
                '<Dial answerOnBridge="true" action="' . $callEndUrl . '" hangupOnStar="true">' .
                    '<Number url="' .  $preconnectUrl . '" method="GET">' . $config->forwardToPhoneNumber() . '</Number>' . 
                '</Dial>' . 
            '</Response>'
        , false);

        Event::assertDispatched(CallEvent::class, function(CallEvent $event) use($company){
            return $company->id === $event->call->company_id && $event->name === Plugin::EVENT_CALL_START;
        }); 
    }

    /**
     *  Test collecting digits failed
     *  
     *  @group incoming-calls
     */
    public function testCollectFailed()
    {
        Event::fake();

        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company, [
           'greeting_enabled'       => false,
           'whisper_enabled'        => false,
           'keypress_enabled'       => true,
           'keypress_error_message'   => 'Keypress Error',
           'keypress_failure_message' => 'Keypress Failed',
           'keypress_key'           => 5,
           'keypress_attempts'      => 3
        ]);

        $phoneNumber = $this->createPhoneNumber($company, $config);

        //
        //  Try with failure
        //
        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $phoneNumber->e164Format()
        ]);

        $variables                 = $config->variables();
        $variables                 = array_merge($variables, json_decode($incomingCall->variables, true));
        $variables['company_name'] = $company->name;
        
        $variables['dialed_cc']           = PhoneNumber::countryCode($phoneNumber->e164Format());
        $variables['dialed_number']       = PhoneNumber::number($phoneNumber->e164Format());
        
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());
        $response->assertHeader('Content-Type', 'application/xml');

        for( $failedAttempts = 0; $failedAttempts < $config->keypress_attempts -1; $failedAttempts++ ){
            $variables['failed_attempts']    = $failedAttempts;
            $variables['remaining_attempts'] = $config->keypress_attempts - $failedAttempts;
            $incomingCall->variables         = json_encode($variables);
            
            $response = $this->json('POST', route('incoming-call-collect', array_merge([
                'company_id'             => $company->id,
                'phone_number_id'        => $phoneNumber->id,
                'phone_number_config_id' => $config->id,
                'failed_attempts'        => $failedAttempts,
                'variables'              => json_encode($variables),
                'Digits'                 => 4
            ],$incomingCall->toArray())));

            $variables['failed_attempts']    = $failedAttempts + 1;
            $variables['remaining_attempts'] = $config->keypress_attempts - ($failedAttempts + 1);
            $expectedUrl = route('incoming-call-collect', [
                'company_id'             => $company->id,
                'phone_number_id'        => $phoneNumber->id,
                'phone_number_config_id' => $config->id,
                'failed_attempts'        => $failedAttempts + 1,
                'variables'              => json_encode($variables),
            ]);

            $response->assertSee(
                '<Response>' .
                    '<Gather numDigits="1" timeout="' . $config->keypress_timeout . '" actionOnEmptyResult="true" method="POST" action="' . 
                    htmlspecialchars($expectedUrl) . '">' .
                        '<Say language="' . $company->tts_language. '" voice="Polly.' . $company->tts_voice . '">' . 
                            'Keypress Error' .
                        '</Say></Gather></Response>'
            , false);

            $response->assertHeader('Content-Type', 'application/xml');

            $response->assertStatus(200);
        }

        $variables['failed_attempts']    = $config->keypress_attempts - 1;
        $variables['remaining_attempts'] = 1;
        $response = $this->json('POST', route('incoming-call-collect', array_merge([
            'company_id'             => $company->id,
            'phone_number_id'        => $phoneNumber->id,
            'phone_number_config_id' => $config->id,
            'failed_attempts'        => $config->keypress_attempts -1,
            'variables'              => json_encode($variables),
            'Digits'                 => 4
        ], $incomingCall->toArray())));

        $response->assertSee(
            '<Response>' .
                '<Say language="' . $company->tts_language. '" voice="Polly.' . $company->tts_voice . '">' . 
                    'Keypress Failed' .
                '</Say>' . 
                '<Reject/>' .
            '</Response>'
        , false);

        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertStatus(200);

        Event::assertNotDispatched(CallEvent::class);
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
                 ->with('GET', $url . '.mp3', [
                     'connection_timeout' => 900
                 ])
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
        $transcriptionPath = 'accounts/' . $call->account_id . '/companies/' . $call->company_id . '/transcriptions/Transcription-' . $call->id . '.txt';
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
                 ->andReturn([
                     'hello_world'
                 ])
                 ->times(2);

            $mock->shouldReceive('deleteTranscription')
                 ->with($jobId)
                 ->once();
        });

        $response = $this->post(route('dialed-call-recording-available', [
            'AccountSid'        => config('services.twilio.sid'),
            'ParentCallSid'     => $call->external_id,
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
        $config      = $this->createConfig($company, [
            'keypress_enabled' => 0
        ]);

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

            'source'                => $session->source,
            'medium'                => $session->medium,
            'content'               => $session->content,
            'campaign'              => $session->campaign,
            'keyword'               => $session->keyword,
            'is_paid'               => $session->is_paid,
            'is_organic'            => $session->is_organic,
            'is_direct'             => $session->is_direct,
            'is_referral'           => $session->is_referral,
            'is_remarketing'        => 0,
            'is_search'             => $session->is_search,
            'recording_enabled'     => $config->recording_enabled,
            'forwarded_to'          => $config->forwardToPhoneNumber(),
            'duration'              => null,
        ]);

        $this->assertDatabaseHas('keyword_tracking_pool_sessions', [
            'id'         => $session->id,
            'contact_id' => $contact->id
        ]);

        Event::assertDispatched(CallEvent::class, function ($event){
            return $event->name == Plugin::EVENT_CALL_START;
        });
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
        $config      = $this->createConfig($company, [
            'keypress_enabled' => 0
        ]);

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
            'source'                => $session2->source,
            'medium'                => $session2->medium,
            'content'               => $session2->content,
            'campaign'              => $session2->campaign,
            'keyword'               => $session2->keyword,
            'is_paid'               => $session2->is_paid,
            'is_organic'            => $session2->is_organic,
            'is_direct'             => $session2->is_direct,
            'is_referral'           => $session2->is_referral,
            'is_remarketing'        => 0,
            'is_search'             => $session2->is_search,
            'recording_enabled'     => $config->recording_enabled,
            'forwarded_to'          => $config->forwardToPhoneNumber(),
            'duration'              => null,
        ]);

        $this->assertDatabaseHas('keyword_tracking_pool_sessions', [
            'id'         => $session2->id,
            'contact_id' => $contact->id
        ]);

        Event::assertDispatched(CallEvent::class, function ($event){
            return $event->name == Plugin::EVENT_CALL_START;
        });
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
        $config      = $this->createConfig($company, [
            'keypress_enabled' => 0
        ]);

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

        $response = 
        $session2 = factory(KeywordTrackingPoolSession::class)->create([
            'contact_id'                => null,
            'keyword_tracking_pool_id'  => $pool->id,
            'phone_number_id'           => $phoneNumber->id,
            'landing_url'               => 'http://' . str_random(10) . '.com?' . http_build_query($params2),
            'source'                    => $params2['utm_source'],
            'medium'                    => $params2['utm_medium'],
            'content'                   => $params2['utm_content'],
            'campaign'                  => $params2['utm_campaign'],
            'keyword'                   => $params2['utm_term'],
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
            'source'                => $session2->source,
            'medium'                => $session2->medium,
            'content'               => $session2->content,
            'campaign'              => $session2->campaign,
            'keyword'               => $session2->keyword,
            'is_paid'               => $session2->is_paid,
            'is_organic'            => $session2->is_organic,
            'is_direct'             => $session2->is_direct,
            'is_referral'           => $session2->is_referral,
            'is_remarketing'        => 0,
            'is_search'             => $session2->is_search,
            'recording_enabled'     => $config->recording_enabled,
            'forwarded_to'          => $config->forwardToPhoneNumber(),
            'duration'              => null,
        ]);

        $this->assertDatabaseHas('keyword_tracking_pool_sessions', [
            'id'         => $session2->id,
            'contact_id' => $contact->id
        ]);

        Event::assertDispatched(CallEvent::class);
    }

    /**
     * Test calling a pool with no active session for a contact that exists with a call to this number is routed
     *
     * @group incoming-calls
     */
    public function testSecondPoolCallWithNoSession()
    {
        Event::fake();

        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company,[
            'keypress_enabled' => 0
        ]);

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
            'source'                => 'Unknown',
            'medium'                => null,
            'content'               => null,
            'campaign'              => null,
            'keyword'               => null,
            'is_paid'               => 0,
            'is_organic'            => 0, 
            'is_direct'             => 0,
            'is_referral'           => 0,
            'is_search'             => 0,
            'recording_enabled'     => $config->recording_enabled,
            'forwarded_to'          => $config->forwardToPhoneNumber(),
            'duration'              => null,
        ]);

        Event::assertDispatched(CallEvent::class);
    }

    /**
     * Test calling a pool with a contact that exists but calling with old unexpired session
     *
     * @group incoming-calls
     */
    public function testSecondPoolCallWithOldSession()
    {
        Event::fake();

        $company     = $this->createCompany();
        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company,[
            'keypress_enabled' => 0
        ]);

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
            'active'                    => 0,
            'ended_at'                  => null
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
            'source'                => $session1->source,
            'medium'                => $session1->medium,
            'content'               => $session1->content,
            'campaign'              => $session1->campaign,
            'keyword'               => $session1->keyword,
            'is_paid'               => $session1->is_paid,
            'is_organic'            => $session1->is_organic,
            'is_direct'             => $session1->is_direct,
            'is_referral'           => $session1->is_referral,
            'is_remarketing'        => 0,
            'is_search'             => $session1->is_search,
            'recording_enabled'     => $config->recording_enabled,
            'forwarded_to'          => $config->forwardToPhoneNumber(),
            'duration'              => null,
        ]);

        Event::assertDispatched(CallEvent::class);
    }
}
