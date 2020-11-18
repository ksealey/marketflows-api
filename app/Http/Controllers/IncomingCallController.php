<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Account;
use App\Models\Plugin;
use App\Models\Company;
use App\Models\Company\Contact;
use App\Models\Company\PhoneNumberConfig;
use App\Models\Company\PhoneNumber;
use App\Models\Company\AudioClip;
use App\Models\BlockedPhoneNumber;
use App\Models\BlockedCall;
use App\Models\Company\Call;
use App\Models\Company\KeywordTrackingPoolSession;
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
     * @param Request $request
     * 
     * @return Response
     */
    public function handleIncomingCall(Request $request)
    {
        $rules = [
            'CallSid'       => 'bail|required|max:64',
            'CallStatus'    => 'bail|required|max:64',
            'Direction'     => 'bail|required|max:64',
            'To'            => 'bail|required|max:16',
            'ToCity'        => 'bail|max:128',
            'ToState'       => 'bail|max:128',
            'ToZip'         => 'bail|max:16',
            'ToCountry'     => 'bail|max:128',
            'From'          => 'bail|required|max:16',
            'FromCity'      => 'bail|max:128',
            'FromState'     => 'bail|max:128',
            'FromZip'       => 'bail|max:16',
            'FromCountry'   => 'bail|max:255'
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        
        $response = new VoiceResponse();

        //  Find out how to handle this call
        $callerCountryCode = PhoneNumber::countryCode($request->From);
        $callerNumber      = PhoneNumber::number($request->From);

        $dialedCountryCode = PhoneNumber::countryCode($request->To);
        $dialedNumber      = PhoneNumber::number($request->To);
        
        $query = PhoneNumber::where('number', $dialedNumber)
                            ->where('country_code', $dialedCountryCode);
        
        $phoneNumber = $query->first();
        if( ! $phoneNumber ){
            $response->reject();

            return Response::xmlResponse($response);
        }

        //
        //  Log and Reject call if blocked
        //
        $query = BlockedPhoneNumber::where('account_id', $phoneNumber->account_id)
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
            $config   = $keywordTrackingPool->phone_number_config;

            //
            //  If this is a keyword tracking pool number, there are no unclaimed active sessions for this number and this contact does not exist, end now
            //
            $sessions = $keywordTrackingPool->activeSessions($phoneNumber->id);
            if( ! count($sessions) ){
                $contact = Contact::where('company_id', $company->id)
                                    ->where('country_code', $callerCountryCode)
                                    ->where('number', $callerNumber)
                                    ->first();
                if( ! $contact ){
                    $response->reject();
                    
                    return Response::xmlResponse($response);
                }
            }
        }else{
            //  
            //  Reject call if number is disabled
            //
            if( $phoneNumber->disabled_at ){
                $response->reject();

                return Response::xmlResponse($response);
            }

            $company = $phoneNumber->company;
            $config  = $phoneNumber->phone_number_config;
        }

        //  Handle greeting
        if( $config->greeting_enabled ){
            if( $config->greeting_message_type === 'TEXT' ){
                $response->say($config->greetingMessage(), [
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
                    'company_id'             => $company->id,
                    'phone_number_id'        => $phoneNumber->id,
                    'phone_number_config_id' => $config->id,
                    'failed_attempts'        => 0,
                ])
            ]);

            return Response::xmlResponse($response);
        }

        $call        = $this->createCall($company, $phoneNumber, $config, $request);
        $dialCommand = $response->dial(null, $this->getDialConfig($config));
        $dialCommand->number(
            $config->forwardToPhoneNumber(), 
            $this->getNumberConfig($config, $call)
        );

        event(new CallEvent(Plugin::EVENT_CALL_START, $call));

        return Response::xmlResponse($response);
    }

    /**
     * Handle collecting digits
     * 
     * @param Request $request
     * 
     * @return Response
     */
    public function handleCollect(Request $request)
    {
        $validator = validator($request->input(), [
            'CallSid'                => 'bail|required|max:64',
            'CallStatus'             => 'bail|required|max:64',
            'Direction'              => 'bail|required|max:64',
            'To'                     => 'bail|required|max:16',
            'ToCity'                 => 'bail|max:128',
            'ToState'                => 'bail|max:128',
            'ToZip'                  => 'bail|max:16',
            'ToCountry'              => 'bail|max:128',
            'From'                   => 'bail|required|max:16',
            'FromCity'               => 'bail|max:128',
            'FromState'              => 'bail|max:128',
            'FromZip'                => 'bail|max:16',
            'FromCountry'            => 'bail|max:255',
            'company_id'             => 'bail|required|numeric',
            'phone_number_id'        => 'bail|required|numeric',
            'phone_number_config_id' => 'bail|required|numeric',
            'failed_attempts'        => 'bail|required|numeric',
            'Digits'                 => 'bail|nullable|numeric',
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $response         = new VoiceResponse();
        $failedAttempts   = intval($request->failed_attempts);

        $company          = Company::find($request->company_id);
        $phoneNumber      = PhoneNumber::find($request->phone_number_id);
        $config           = PhoneNumberConfig::find($request->phone_number_config_id); 

        //  If user did not enter the correct key
        if( ! $request->filled('Digits') || $request->Digits != $config->keypress_key ){
            ++$failedAttempts;
            if( $failedAttempts >= intval($config->keypress_attempts)){
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
                    'company_id'             => $request->company_id,
                    'phone_number_id'        => $request->phone_number_id,
                    'phone_number_config_id' => $request->phone_number_config_id,
                    'failed_attempts'        => $failedAttempts,
                ])
            ]);

            if( $config->keypress_message_type === 'AUDIO' ){
                $audioClip = AudioClip::find($config->keypress_audio_clip_id);
                $gather->play($audioClip->url);
            }elseif( $config->keypress_message_type === 'TEXT' ){
                $gather->say($config->keypressMessage(), [
                    'language' => $company->tts_language,
                    'voice'    => 'Polly.' . $company->tts_voice
                ]);
            }

            return Response::xmlResponse($response);
        }

        //  Valid input, forward call
        $call        = $this->createCall($company, $phoneNumber, $config, $request);
        $dialCommand = $response->dial(null, $this->getDialConfig($config));
        $dialCommand->number(
            $config->forwardToPhoneNumber(), 
            $this->getNumberConfig($config, $call)
        );

        event(new CallEvent(Plugin::EVENT_CALL_START, $call));

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
     * Handle a completed call
     *
     * @param Request $request
     * 
     * @return Response
     */
    public function handleCompletedCall(Request $request)
    {
        $rules = [
            'CallSid'       => 'required|max:64',
            'DialCallStatus'=> 'required|max:64'
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $call           = Call::where('external_id', $request->CallSid)->first();
        $call->status   = $this->callStatus($request->DialCallStatus);
        $call->save();

        return Response::xmlResponse(new VoiceResponse());
    }

    /**
     * Handle getting a complete call duration
     * 
     * @param Request $request
     * 
     * @return Response
     */
    public function handleCompletedCallDuration(Request $request)
    {
        $rules = [
            'CallSid'       => 'required|max:64',
            'CallDuration'  => 'required|max:64'
        ];

        $call = Call::where('external_id', $request->CallSid)->first();
        if( ! $call )
            return Response::xmlResponse(new VoiceResponse());
            
        $call->duration = intval($request->CallDuration);
        $call->save();

        event(new CallEvent(Plugin::EVENT_CALL_END, $call));

        return Response::xmlResponse(new VoiceResponse());
    }

    /**
     * Handle downloading a call recording
     * 
     * @param Request $request
     * 
     * @return Response
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
        $dialConfig = [
            'answerOnBridge' => 'true',
            'action'         => route('incoming-call-completed')
        ];

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

    protected function fieldFormat($data)
    {
        if( ! $data ) return null;

        return substr(ucwords(strtolower($data)), 0, 64);
    }

    protected function createCall($company, $phoneNumber, $config, Request $request)
    {
        $callerCountryCode = PhoneNumber::countryCode($request->From);
        $callerNumber      = PhoneNumber::number($request->From);

        $keywordTrackingPool = $phoneNumber->keyword_tracking_pool_id ? $phoneNumber->keyword_tracking_pool : null;
        $session             = null;

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
                //  Get unassigned sessions for this phone number
                //
                $sessions = $keywordTrackingPool->activeSessions($phoneNumber->id);
                $session  = count($sessions) ? $sessions->first() : null;
                if( $session ){
                    $gUUID = $session->guuid;   
                }
            }

            //
            //  Create a new contact
            //
            $firstCall  = true;
            $callerName = strtolower($request->CallerName ?: null);
            if( ! $callerName ){
                $callerName  = trim($request->FromCity  ?: '') . ' ' . ($request->FromState ?: '');
            }
            
            $callerName       = strtolower(str_replace(',', ' ', ($callerName ? $callerName : 'Unknown Caller')));
            $callerNamePieces = explode(' ', $callerName);

            $contact = Contact::create([
                'uuid'          => $gUUID,
                'account_id'    => $company->account_id,
                'company_id'    => $company->id,
                'first_name'    => $this->fieldFormat($callerNamePieces[1]),
                'last_name'     => $this->fieldFormat($callerNamePieces[0] ?? ''),
                'email'         => null,
                'country_code'  => $callerCountryCode,
                'number'        => $callerNumber,
                'city'          => $this->fieldFormat($request->FromCity) ?: null,
                'state'         => strtoupper($this->fieldFormat($request->FromState)) ?: null,
                'zip'           => $this->fieldFormat($request->FromZip) ?: null,
                'country'       => strtoupper($this->fieldFormat($request->FromCountry)) ?: null
            ]);

            if( $session ){ // Claim session
                $session->contact_id = $contact->id; 
                $session->save();
            }
        }else{
            //  This person has called before
            $firstCall = ! Call::where('contact_id', $contact->id)->count();

            if( $keywordTrackingPool ){
                //  First look for claimed session
                $sessions = $keywordTrackingPool->activeSessions($phoneNumber->id, $contact->id, false);
                $session  = count($sessions) ? $sessions->first() : null;
                
                //  Look for the last session this user had that isn't expired
                $session = $keywordTrackingPool->lastUnexpiredSession($phoneNumber->id, $contact->id);
                
                //  Look for unclaimed session
                if( ! $session ){
                    $sessions = $keywordTrackingPool->activeSessions($phoneNumber->id);
                    $session  = count($sessions) ? $sessions->first() : null;
                    if( $session ){ // Claim session
                        $contact->uuid       = $session->guuid;  //  Update contact to have new guuid
                        $session->contact_id = $contact->id; 

                        $contact->save();
                        $session->save();
                    }
                }
            }
        }

        //
        //  Determine how to route this call and capture sourcing data
        //  
        if( $keywordTrackingPool ){
            if( $session ){
                $source     = $session->source;
                $medium     = $session->medium;
                $content    = $session->content;
                $campaign   = $session->campaign;
                $keyword    = $session->keyword;
                $isOrganic  = $session->is_organic;
                $isPaid     = $session->is_paid;
                $isDirect   = $session->is_direct;
                $isReferral = $session->is_referral;
                $isSearch   = $session->is_search;
            }else{
                $source     = 'Unknown';
                $medium     = null;
                $content    = null;
                $campaign   = null;
                $keyword    = null;
                $isOrganic  = false;
                $isPaid     = false;
                $isDirect   = false;
                $isReferral = false;
                $isSearch   = false;
            }

            $config = $keywordTrackingPool->phone_number_config;
        }else{
            $source     = $phoneNumber->source;
            $medium     = $phoneNumber->medium ?: null;
            $content    = $phoneNumber->content ?: null;
            $campaign   = $phoneNumber->campaign ?: null;
            $keyword    = null;
            $isOrganic  = $phoneNumber->is_organic;
            $isPaid     = $phoneNumber->is_paid;
            $isDirect   = $phoneNumber->is_direct;
            $isReferral = $phoneNumber->is_referral;
            $isSearch   = $phoneNumber->is_search;

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
            'direction'                         => 'Inbound',
            'status'                            => $this->callStatus($request->CallStatus),
            'source'                            => $source,
            'medium'                            => $medium,
            'content'                           => $content,
            'campaign'                          => $campaign,
            'keyword'                           => $keyword,
            'is_paid'                           => $isPaid,
            'is_organic'                        => $isOrganic,
            'is_direct'                         => $isDirect,
            'is_referral'                       => $isReferral,
            'is_search'                         => $isSearch,
            'recording_enabled'                 => $config->recording_enabled,
            'transcription_enabled'             => $config->transcription_enabled,
            'forwarded_to'                      => $config->forwardToPhoneNumber(),
            'created_at'                        => now()->format('Y-m-d H:i:s.u'),
            'updated_at'                        => now()->format('Y-m-d H:i:s.u')
        ]);

        $call->company = $company;
        $call->contact = $contact;

        return $call;
    }

    public function callStatus($status)
    {
        return substr(ucwords(strtolower(str_replace('-', ' ', $status))), 0, 64);
    }
}
