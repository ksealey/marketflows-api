<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Account;
use App\Models\Company\Contact;
use App\Models\Company\PhoneNumberConfig;
use App\Models\Company\PhoneNumber;
use App\Models\Company\AudioClip;
use App\Models\BlockedPhoneNumber;
use App\Models\BlockedCall;
use App\Models\Company\Call;
use App\Models\Company\KeywordTrackingPoolSession;
use App\Models\Company\Webhook;
use App\Events\Company\CallEvent;
use Twilio\TwiML\VoiceResponse;
use Twilio\Rest\Client as Twilio;
use \App\Services\PhoneNumberService;
use \App\Jobs\ProcessCallRecordingJob;
use Validator;
use Storage;
use Exception;
use DB;

class IncomingCallController extends Controller
{
    /**
     * Entry point for all new incoming calls
     * 
     */
    public function handleCall(Request $request)
    {
        $rules = [
            'CallSid'       => 'required|max:64',
            'CallStatus'    => 'required|max:64',
            'Direction'     => 'required|max:64',
            'To'            => 'required|max:16',
            'ToCity'        => 'max:128',
            'ToState'       => 'max:128',
            'ToZip'         => 'max:16',
            'ToCountry'     => 'max:128',
            'From'          => 'required|max:16',
            'FromCity'      => 'max:128',
            'FromState'     => 'max:128',
            'FromZip'       => 'max:16',
            'FromCountry'   => 'max:255'
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        
        $response = new VoiceResponse();

        //  Find out how to handle this call
        $dialedCountryCode = PhoneNumber::countryCode($request->To);
        $dialedNumber      = PhoneNumber::number($request->To);
        

        $query = PhoneNumber::where('number', $dialedNumber); 
        if( $dialedCountryCode )
            $query->where('country_code', $dialedCountryCode);
        
        //  If we don't recognize this call, end it
        $keywordTrackingPool = null;
        $session             = null;
        $phoneNumber         = $query->first();

        if( ! $phoneNumber ){
            $response->reject();

            return Response::xmlResponse($response);
        }

        //
        //  Log and Reject call if blocked
        //
        $callerCountryCode = PhoneNumber::countryCode($request->From) ?: $company->country_code;
        $callerNumber      = PhoneNumber::number($request->From);
        $query             = BlockedPhoneNumber::where('account_id', $phoneNumber->account_id)
                                               ->where('number', $callerNumber)
                                               ->where('country_code', $callerCountryCode);
        
        $blockedPhoneNumber = $query->first();
        if( $blockedPhoneNumber ){
            BlockedCall::create([
                'account_id'              => $phoneNumber->account_id,
                'blocked_phone_number_id' => $blockedPhoneNumber->id,
                'phone_number_id'         => $phoneNumber->id,
                'created_at'              => now()
            ]);

            $response->reject();

            return Response::xmlResponse($response);
        }

        if( $phoneNumber->keyword_tracking_pool_id ){
            //  
            //  Reject call if keyword tracking pool is disabled
            //
            $keywordTrackingPool = $phoneNumber->keyword_tracking_pool;
            if( ! $keywordTrackingPool || $keywordTrackingPool->disabled_at ){
                $response->reject();
                
                return Response::xmlResponse($response);
            }

            $company  = $keywordTrackingPool->company;
        }else{
            //  
            //  Reject call if number is disabled
            //
            if( $phoneNumber->disabled_at ){
                $response->reject();

                return Response::xmlResponse($response);
            }

            $company = $phoneNumber->company;
        }

        //
        //  Look for contact and create if not exists
        //
        $cleanFullPhone = $callerCountryCode . $callerNumber;
        $contact = Contact::where('company_id', $company->id)
                          ->where('country_code', $callerCountryCode)
                          ->where('number', $callerNumber)
                          ->first();

        if( ! $contact ){
            //
            //  If this is from a keyword tracking pool and there are no active sessions, end here
            //
            $gUUID = Str::uuid();
            if( $keywordTrackingPool ){
                //
                //  Get uassigned sessions for this phone number
                //
                $sessions = $keywordTrackingPool->activeSessions($phoneNumber->id, true);
                $session  = count($sessions) ? $sessions->first() : null;
                
                //
                //  If there are no active sessions for this phone number, end here
                //
                if( ! $session ){
                    $response->reject();

                    return Response::xmlResponse($response);
                }

                $gUUID = $session->guuid;
            }

            //
            //  Create a new contact
            //
            $firstCall  = true;
            $callerName = $request->CallerName ?: null;
            if( ! $callerName ){
                $callerName  = trim(strtolower($request->FromCity  ?: '') . ' ' . ($request->FromState ?: ''));
            }
            
            $callerName       = str_replace(',', ' ', ($callerName ? $callerName : 'Unknown Caller'));
            $callerNamePieces = explode(' ', $callerName);

            $contact = Contact::create([
                'uuid'          => $gUUID,
                'account_id'    => $company->account_id,
                'company_id'    => $company->id,
                'first_name'    => $callerNamePieces[0],
                'last_name'     => !empty($callerNamePieces[1]) ? $callerNamePieces[1] : '',
                'email'         => null,
                'country_code'  => $callerCountryCode,
                'number'        => $callerNumber,
                'city'          => $request->FromCity ? substr($request->FromCity, 0, 64) : null,
                'state'         => $request->FromState ? substr($request->FromState, 0, 64) : null,
                'zip'           => $request->FromZip ? substr($request->FromZip, 0, 64) : null,
                'country'       => $request->FromCountry ? substr($request->FromCountry, 0, 64) : null
            ]);

            if( $session ){ // Claim session
                $session->contact_id = $contact->id; 
                $session->save();
            }
        }else{
            //  This person has called before
            $firstCall = ! Call::where('contact_id', $contact->id)->count();

            if( $keywordTrackingPool ){
                $session = $contact->activeSession($phoneNumber->id);
                
                if( ! $session ){
                    $sessions = $keywordTrackingPool->activeSessions($phoneNumber->id, true);
                    $session  = count($sessions) ? $sessions->first() : null;
                    if( $session ){ // Claim session
                        $session->contact_id = $contact->id; 
                        $session->guuid      = $contact->uuid;
                        $session->save();
                    }
                }
            }
        }

        //
        //  Determine how to route this call and capture sourcing data
        //  
        $source     = null;
        $medium     = null;
        $content    = null;
        $campaign   = null;
        $keyword    = null;
        $isOrganic  = null;
        $isPaid     = null;
        $isDirect   = null;
        $isReferral = null;

        if( $keywordTrackingPool ){
            if( $session ){
                $source = $session->getSource($company->source_param, $company->source_referrer_when_empty);
                $source = $source ? substr($source, 0, 512) : null;

                $medium = $session->getMedium($company->medium_param);
                $medium = $medium ? substr($medium, 0, 128) : null;

                $content = $session->getContent($company->content_param);
                $content = $content ? substr($content, 0, 128) : null;

                $campaign = $session->getCampaign($company->campaign_param);
                $campaign = $campaign ? substr($campaign, 0, 128) : null;

                $keyword = $session->getKeyword($company->keyword_param);
                $keyword = $keyword ? substr($keyword, 0, 128) : null;

                $isOrganic  = $session->getIsOrganic($company->medium_param);
                $isPaid     = $session->getIsPaid($company->medium_param);
                $isDirect   = $session->getIsDirect();
                $isReferral = $session->getIsReferral();
            }

            $config = $keywordTrackingPool->phone_number_config;
        }else{
            $source     = $phoneNumber->source;
            $medium     = $phoneNumber->medium ?: null;
            $content    = $phoneNumber->content ?: null;
            $campaign   = $phoneNumber->campaign ?: null;

            $config     = $phoneNumber->phone_number_config;
        }

        //
        //  Log call
        //
        $call = Call::create([
            'account_id'                        => $company->account_id,
            'company_id'                        => $company->id,
            'contact_id'                        => $contact->id,
            'phone_number_id'                   => $phoneNumber->id,
            'phone_number_name'                 => $phoneNumber->name,
            'type'                              => $phoneNumber->type,
            'category'                          => $phoneNumber->category,
            'sub_category'                      => $phoneNumber->sub_category,
            'keyword_tracking_pool_id'          => $keywordTrackingPool ? $keywordTrackingPool->id : null,
            'keyword_tracking_pool_name'        => $keywordTrackingPool ? $keywordTrackingPool->name : null,
            'keyword_tracking_pool_session_id'  => $session ? $session->id : null,
            'first_call'                        => $firstCall,
            'external_id'                       => $request->CallSid,
            'direction'                         => substr(ucfirst(strtolower($request->Direction)), 0, 16),
            'status'                            => substr(ucfirst(strtolower($request->CallStatus)), 0, 64),
            'source'                            => $source,
            'medium'                            => $medium,
            'content'                           => $content,
            'campaign'                          => $campaign,
            'keyword'                           => $keyword,
            'is_organic'                        => $isOrganic,
            'is_paid'                           => $isPaid,
            'is_direct'                         => $isDirect,
            'is_referral'                       => $isReferral,
            'recording_enabled'                 => $config->recording_enabled,
            'transcription_enabled'             => $config->transcription_enabled,
            'forwarded_to'                      => $config->forwardToPhoneNumber(),
            'created_at'                        => now()->format('Y-m-d H:i:s.u'),
            'updated_at'                        => now()->format('Y-m-d H:i:s.u')
        ]);

        event(new CallEvent(Webhook::ACTION_CALL_START, $call, $contact, $company));

        //
        //  Handle recording, greeting, keypad entry, forwarding, and whisper message
        //
    
        //  Handle greeting
        if( $config->greeting_enabled ){
            if( $config->greeting_message_type === 'TEXT' ){
                $response->say($config->greetingMessage($call), [
                    'language' => $company->tts_language,
                    'voice'    => 'Polly.' . $company->tts_voice
                ]);
            }elseif( $config->greeting_message_type === 'AUDIO' ){
                $audioClip = AudioClip::find($config->greeting_audio_clip_id);
                $response->play($audioClip->url);
            }
        }

        //  Handle keypad entry, sending response now
        if( $config->keypress_enabled ){
            $response->gather([
                'numDigits'             => 1,
                'timeout'               => $config->keypress_timeout,
                'actionOnEmptyResult'   => true,
                'method'                => 'POST',
                'action'    => route('incoming-call-collect', [
                    'call_id'                => $call->id,
                    'phone_number_config_id' => $config->id,
                    'keypress_attempts'      => $config->keypress_attempts,
                    'keypress_key'           => $config->keypress_key,
                    'failed_attempts'        => 0,
                ])
            ]);

            return Response::xmlResponse($response);
        }

        $dialCommand = $response->dial(null, $this->getDialConfig($config));
        $dialCommand->number(
            $config->forwardToPhoneNumber(), 
            $this->getNumberConfig($config, $call)
        );

        return Response::xmlResponse($response);
    }

    /**
     * Handle a call changing it's status
     * 
     * @param Request $request
     * 
     * @return Response
     */
    public function handleCallStatusChanged(Request $request)
    {
        $rules = [
            'CallSid'       => 'required|max:64',
            'CallStatus'    => 'required|max:64',
            'CallDuration'  => 'numeric'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        $call = Call::where('external_id', $request->CallSid)
                    ->first();

        if( ! $call )
            return response('No Content', 204);

        //  Update call  
        $call->status   = substr(ucfirst(strtolower($request->CallStatus)), 0, 64);
        $call->duration = intval($request->CallDuration) ?: null;
        $call->save();

        $isComplete = trim(strtolower($request->CallStatus)) == 'completed' ? true : false;

        event(new CallEvent($isComplete ? Webhook::ACTION_CALL_END : Webhook::ACTION_CALL_UPDATED, $call));
    }


    /**
     * Handle collecting digits
     * 
     */
    public function handleCollect(Request $request)
    {
        $validator = validator($request->input(), [
            'call_id'                => 'bail|required|numeric',
            'phone_number_config_id' => 'bail|required|numeric',
            'keypress_attempts'      => 'bail|required|numeric',
            'keypress_key'           => 'bail|required|numeric',
            'failed_attempts'        => 'bail|required|numeric',
            'Digits'                 => 'bail|nullable|numeric'
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $response         = new VoiceResponse();
        $keypressAttempts = intval($request->keypress_attempts);
        $keypressKey      = intval($request->keypress_key);
        $failedAttempts   = intval($request->failed_attempts);
        $config           = PhoneNumberConfig::find($request->phone_number_config_id); 
        $call             = Call::find($request->call_id);
        $company          = $call->company;

        //  If user did not enter the correct key
        if( ! $request->filled('Digits') || intval($request->Digits) != $keypressKey ){
            ++$failedAttempts;
            if( $failedAttempts >= $keypressAttempts ){
                $response->reject();
                return Response::xmlResponse($response);  // End call           
            }

            //  Let the user try again
            $gather = $response->gather([
                'numDigits'             => 1,
                'timeout'               => $config->keypress_timeout,
                'actionOnEmptyResult'   => true,
                'method'                => 'POST',
                'action'    => route('incoming-call-collect', [
                    'call_id'                => $request->call_id,
                    'phone_number_config_id' => $request->phone_number_config_id,
                    'keypress_attempts'      => $request->keypress_attempts,
                    'keypress_key'           => $request->keypress_key,
                    'failed_attempts'        => $failedAttempts,
                ])
            ]);

            if( $config->keypress_message_type === 'AUDIO' ){
                $audioClip = AudioClip::find($config->keypress_audio_clip_id);
                $gather->play($audioClip->url);
            }elseif( $config->keypress_message_type === 'TEXT' ){
                $gather->say($config->keypressMessage($call), [
                    'language' => $company->tts_language,
                    'voice'    => 'Polly.' . $company->tts_voice
                ]);
            }

            return Response::xmlResponse($response);
        }

        //  Valid input, forward call
        $dialCommand = $response->dial(null, $this->getDialConfig($config));
        $dialCommand->number(
            $config->forwardToPhoneNumber(), 
            $this->getNumberConfig($config, $call)
        );

        return Response::xmlResponse($response);
    }

    /**
     * Handle generating a whisper message
     * 
     * @param Request $request
     * 
     * @return Response
     */
    public function handleCallWhisper(Request $request)
    {
        $config   = [];
        if( $request->whisper_language )
            $config['language'] = $request->whisper_language;

        if( $request->whisper_voice )
            $config['voice'] = 'Polly.' . $request->whisper_voice;

        $response = new VoiceResponse();
        $response->say($request->whisper_message, $config);

        return Response::xmlResponse($response);
    }

    /**
     * Handle downloading a call recording
     * 
     */
    public function handleRecordingAvailable(Request $request)
    {
        $rules = [
            'CallSid'           => 'required|max:64',
            'RecordingSid'      => 'required|max:64',
            'RecordingUrl'      => 'required',
            'RecordingDuration' => 'required|numeric'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        $call = Call::where('external_id', $request->CallSid)->first();
        if( ! $call ){
            return response([
                'error' => 'Not found'
            ], 404);
        }

        //  Push job to store recording and transcribe ir neccessary
        ProcessCallRecordingJob::dispatch(
            $call,
            $request->RecordingUrl, 
            $request->RecordingSid,
            $request->RecordingDuration
        );
    }


    protected function getDialConfig($phoneNumberConfig)
    {
        $dialConfig = ['answerOnBridge' => 'true'];

        //  Handle recording
        if( $phoneNumberConfig->recording_enabled ){
            $dialConfig['record']                       = 'record-from-ringing-dual';
            $dialConfig['recordingStatusCallback']      = route('incoming-call-recording-available');
            $dialConfig['recordingStatusCallbackEvent'] = 'completed';
        }else{
            $dialConfig['record'] = 'do-not-record';
        }

        return $dialConfig;
    }

    protected function getNumberConfig($phoneNumberConfig, $call)
    {
        $numberConfig = [];
        if( $phoneNumberConfig->whisper_enabled ){
            $company = $call->company;
            $numberConfig = [
                'url' => route('incoming-call-whisper', [
                    'whisper_message'  => $phoneNumberConfig->whisperMessage($call),
                    'whisper_language' => $company->tts_language,
                    'whisper_voice'    => $company->tts_voice
                ]),
                'method' => 'GET'
            ];
        }
        return $numberConfig;
    }
}
