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
use App;

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
        $callerCountryCode = $this->callerCountryCode($request);
        $callerNumber      = $this->callerPhoneNumber($request);

        $dialedCountryCode = $this->dialedCountryCode($request);
        $dialedNumber      = $this->dialedPhoneNumber($request);
        
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

        //  Get variables provided by request
        $variables = $this->variablesFromRequest($request, [
            'failed_attempts'    => 0,
            'remaining_attempts' => $config->keypress_attempts
        ]);

        //
        //  Handle greeting
        //
        if( $config->greeting_enabled ){
            if( $config->greeting_message_type === 'TEXT' ){
                $response->say($config->message('greeting_message', $variables), [
                    'language' => $company->tts_language,
                    'voice'    => 'Polly.' . $company->tts_voice
                ]);
            }elseif( $config->greeting_message_type === 'AUDIO' ){
                $audioClip = AudioClip::find($config->greeting_audio_clip_id);
                $response->play($audioClip->url);
            }
        }

        //
        //  Handle keypad entry
        //
        if( $config->keypress_enabled ){
            //  Directions
            $response->say($config->message('keypress_directions_message', $variables), [
                'language' => $company->tts_language,
                'voice'    => 'Polly.' . $company->tts_voice
            ]);

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
                    'variables'              => json_encode($variables)
                ])
            ]);

            return Response::xmlResponse($response);
        }

        //  Create the record
        $call = $this->createCall($company, $phoneNumber, $config, $request);

        $dial = $response->dial('', $this->getDialConfig($config, $call));
        $dial->number(
            $config->forwardToPhoneNumber($company->country), 
            $this->getDialedNumberConfig($config, $call, $variables)
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
        $response         = new VoiceResponse();

        $failedAttempts   = intval($request->failed_attempts);
        $company          = Company::find($request->company_id);
        $phoneNumber      = PhoneNumber::find($request->phone_number_id);
        $config           = PhoneNumberConfig::find($request->phone_number_config_id); 
        $variables        = json_decode($request->variables, true);
       

        //  If user did not enter the correct key
        if( ! $request->filled('Digits') || $request->Digits != $config->keypress_key ){
            ++$failedAttempts;
            $variables['failed_attempts']    = $failedAttempts;
            $variables['remaining_attempts'] = $config->keypress_attempts - $failedAttempts;
            if( $failedAttempts >= intval($config->keypress_attempts)){
                //
                //  Play failure message and end call
                //
                if( $config->keypress_failure_message ){
                    $message = $config->message('keypress_failure_message', $variables);
                    $response->say($message, [
                        'language' => $company->tts_language,
                        'voice'    => 'Polly.' . $company->tts_voice
                    ]);
                }
                $response->reject();

                return Response::xmlResponse($response);  // End call           
            }

            //  Let the user try again
            $response->say($config->message('keypress_error_message', $variables), [
                'language' => $company->tts_language,
                'voice'    => 'Polly.' . $company->tts_voice
            ]);
            
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
                    'variables'              => json_encode($variables)
                ])
            ]);

            return Response::xmlResponse($response);
        }

        //  Valid input, forward call
        $call = $this->createCall($company, $phoneNumber, $config, $request);
        $dial = $response->dial('', $this->getDialConfig($config, $call));
        $dial->number(
            $config->forwardToPhoneNumber($company->country), 
            $this->getDialedNumberConfig($config, $call, $variables)
        );

        event(new CallEvent(Plugin::EVENT_CALL_START, $call));

        return Response::xmlResponse($response);
    }

    /**
     * Handle a dialed call's preconnect
     * 
     * @param Request $request
     */
    public function handleDialedCallPreconnect(Request $request)
    {
        $response = new VoiceResponse();

        if( $request->whisper_enabled ){
            $response->say($request->whisper_message, [
                'language' => $request->whisper_language,
                'voice'    => 'Polly.' . $request->whisper_voice
            ]);
        }

        $response->redirect(route('dialed-call-agent-join-conference', [
            'recording_enabled'             => $request->recording_enabled,
            'phone_number_config_id'        => $request->phone_number_config_id,
            'variables'                     => $request->variables
        ]), [
            'method' => 'GET'
        ]);

        return $response;
    }

    /**
     * Handle dialed agent joining a conference
     * 
     */
    public function handleDialedCallAgentJoinConference(Request $request)
    {
        //
        //  Move child call to conference
        //
        $response = new VoiceResponse();
        
        if( $request->recording_enabled ){
            $dialConfig = [
                'hangupOnStar'                  => true,
                'record'                        => 'record-from-ringing-dual',
                'recordingStatusCallbackEvent'  => 'completed',
                'recordingStatusCallback'       => route('dialed-call-recording-available', [
                    'ParentCallSid'             => $request->ParentCallSid
                ])
            ];
        }else{
            $dialConfig = [
                'hangupOnStar' => true
            ];
        }

        $dial = $response->dial('', $dialConfig);
        $dial->conference($request->ParentCallSid, [
            'startConferenceOnEnter' => true,
            'endConferenceOnExit'    => true,
            'beep'                   => false,
            'participantLabel'       => 'Agent'
        ]);

        $config = PhoneNumberConfig::find($request->phone_number_config_id);
        if( $config ){
            $variables = json_decode($request->variables, true) ?: [];
            $company = $config->company;
            if( $config->keypress_conversion_enabled ){
                $variables['remaining_attempts'] = $config->keypress_conversion_attempts;
                $variables['failed_attempts']    = 0;

                $response->say(
                    $config->message('keypress_conversion_directions_message', $variables), [
                        'language' => $company->tts_language,
                        'voice'    => 'Polly.' . $company->tts_voice
                    ]
                );

                $response->gather([
                    'numDigits'             => 1,
                    'timeout'               => $config->keypress_conversion_timeout,
                    'actionOnEmptyResult'   => true,
                    'method'                => 'POST',
                    'action'    => route('incoming-call-collect-conversion', [
                        'company_id'             => $company->id,
                        'failed_attempts'        => 0,
                        'variables'              => json_encode($variables)
                    ])
                ]);
            }
        }

        $twilio = App::make(Twilio::class);
        $twilio->calls($request->CallSid)
               ->update([
                   'twiml' => $response
                ]);

        return $response;
    }

    public function handleDialedCallEnded(Request $request)
    {
        $call     = Call::where('external_id', $request->CallSid)->first();
        $response = new VoiceResponse();

        if( $request->DialCallStatus === 'completed' || $request->DialCallStatus === 'answered' ){
            //  Promote call to in-progress
            $call->status = 'In Progress';

            $dial = $response->dial('', [
                'hangupOnStar' => true
            ]);
            $dial->conference($request->CallSid, [
                'beep'                => false,
                'participantLabel'    => 'Caller',
                'endConferenceOnExit' => true,
            ]);
        }else{
            $call->status = $this->callStatus($request->DialCallStatus);
        }

        $call->save();

        return Response::xmlResponse($response);
    }

    public function handleCollectConversion(Request $request)
    {

    }

    public function handleCleanup(Request $request)
    {
        $response = new VoiceResponse();

        $call = Call::where('external_id', $request->CallSid)->first(); 
        if( ! $call ){
            return Response::xmlResponse($response);
        }

        $call->duration = intval($request->CallDuration);
        if( $call->status === 'Ringing' ){
            $call->status = 'Abandoned';
        }
        if( $call->status === 'In Progress' ){
            $call->status  = 'Completed';
        }

        $call->save();

        return Response::xmlResponse($response);
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

        $call = Call::where('external_id', $request->ParentCallSid)->first();

        //  Push job to store recording and transcribe ir neccessary
        ProcessCallRecordingJob::dispatch(
            $call,
            $request->RecordingUrl, 
            $request->RecordingSid,
            $request->RecordingDuration
        );

        return new VoiceResponse();
    }

    protected function getDialConfig($config, $call)
    {
        return [
            'answerOnBridge' => 'true',
            'action'         => route('dialed-call-ended'),
            'hangupOnStar'   => true
        ];

    }

    protected function getDialedNumberConfig($config, $call, $variables)
    {
        $variables = array_merge($variables, [
            'source'    => $config->messageSource($call->source),
            'medium'    => $call->medium,
            'content'   => $call->content,
            'campaign'  => $call->campaign,
            'keyword'   => $call->keyword
        ]);

        $params = [
            'phone_number_config_id'        => $config->id,
            'recording_enabled'             => $config->recording_enabled,
            'keypress_conversion_enabled'   => $config->keypress_conversion_enabled,
            'keypress_qualification_enabled'=> $config->keypress_qualification_enabled,
            'variables'                     => json_encode($variables)
        ];

        if( $config->whisper_enabled ){
            $company = $config->company;
            $params  = array_merge([
                'whisper_enabled'  => 1,
                'whisper_message'  => $config->message('whisper_message', $variables),
                'whisper_language' => $company->tts_language,
                'whisper_voice'    => $company->tts_voice
            ], $params);
        }

        $numberConfig = [
            'url'                  => route('dialed-call-preconnect', $params), 
            'method'               => 'GET',
            /*'statusCallbackEvent'  => 'answered completed',
            'statusCallback'       => route('dialed-call-number-status-updated'),
            'statusCallbackMethod' => 'POST',*/
        ];
        
        return $numberConfig;
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

            list($firstName, $lastName) = $this->callerName($request);

            $contact = Contact::create([
                'uuid'          => $gUUID,
                'account_id'    => $company->account_id,
                'company_id'    => $company->id,
                'first_name'    => $firstName,
                'last_name'     => $lastName ?: null,
                'email'         => null,
                'country_code'  => $callerCountryCode,
                'number'        => $callerNumber,
                'city'          => $this->callerCity($request),
                'state'         => $this->callerState($request),
                'zip'           => $this->callerZip($request),
                'country'       => $this->callerCountry($request),
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
            $isRemarketing = null;
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
            $isRemarketing = $phoneNumber->is_remarketing;
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
            'status'                            => 'Ringing',
            'source'                            => $source,
            'medium'                            => $medium,
            'content'                           => $content,
            'campaign'                          => $campaign,
            'keyword'                           => $keyword,
            'is_paid'                           => $isPaid,
            'is_organic'                        => $isOrganic,
            'is_direct'                         => $isDirect,
            'is_referral'                       => $isReferral,
            'is_remarketing'                    => $isRemarketing,
            'is_search'                         => $isSearch,
            'recording_enabled'                 => $config->recording_enabled,
            'transcription_enabled'             => $config->transcription_enabled,
            'forwarded_to'                      => $config->forwardToPhoneNumber($company->country),
            'created_at'                        => now()->format('Y-m-d H:i:s.u'),
            'updated_at'                        => now()->format('Y-m-d H:i:s.u')
        ]);

        $call->company = $company;
        $call->contact = $contact;

        return $call;
    }

    public function callStatus($status)
    {
        switch($status)
        {
            case 'queued':
                return 'Queued';
            case 'initiated':
                return 'Initiated';
            case 'ringing':
                return 'Ringing';
            case 'answered':
            case 'in-progress':
                return 'In Progress';
            case 'completed':
                return 'Completed'; 
            case 'no-answer':
                return 'Unanswered';
            case 'busy':
                return 'Busy';
            case 'canceled':
                return 'Abandoned';
            case 'failed':
                return 'Failed';
            

            default:
                return substr(ucwords(strtolower(str_replace('-', ' ', $status))), 0, 64);
        }
    }

    public function callerName(Request $request)
    {
        $firstName  = '';
        $lastName   = ''; 
        $callerName = trim(str_replace(',', ' ', $request->CallerName ?: ''));
        if( ! $callerName ){
            $callerName  = trim($request->FromState . ' ' . $request->FromCity);
        }

        if( $callerName ){
            $callerName = ucwords(strtolower($callerName));
            $callerNamePieces = explode(' ', $callerName);
            if( count($callerNamePieces) === 1 ){
                $firstName = $callerNamePieces[0];
            }else{
                $firstName = $callerNamePieces[1];
                $lastName  = $callerNamePieces[0];
            }
        }else{
            $firstName = 'Unknown';
            $lastName  = 'Caller';
        }

        $firstName = substr($firstName, 0, 64);
        $lastName  = substr($lastName, 0, 64);

        return [$firstName, $lastName];
    } 

    public function callerCountryCode(Request $request)
    {
        return PhoneNumber::countryCode($request->From);
    }

    public function callerPhoneNumber(Request $request)
    {
        return PhoneNumber::number($request->From);
    }

    public function dialedCountryCode(Request $request)
    {
        return PhoneNumber::countryCode($request->To);
    }

    public function dialedPhoneNumber(Request $request)
    {
        return PhoneNumber::number($request->To);
    }
    

    public function callerCity(Request $request)
    {
        return substr(ucwords(strtolower($request->FromCity?:'')), 0, 64) ?: null;
    }

    public function callerState(Request $request)
    {
        return substr(strtoupper($request->FromState?:''), 0, 64) ?: null;
    }

    public function callerZip(Request $request)
    {
        return substr($request->FromZip?:'', 0, 64) ?: null;
    }

    public function callerCountry(Request $request)
    {
        return substr(strtoupper($request->FromCountry?:''), 0, 64) ?: null;
    }

    public function variablesFromRequest(Request $request, $with = [])
    {
        list($firstName,$lastName) = $this->callerName($request);

        return array_merge([
            'caller_first_name'     => $firstName,
            'caller_last_name'      => $lastName,
            'caller_city'           => $this->callerCity($request),
            'caller_state'          => $this->callerState($request),
            'caller_zip'            => $this->callerZip($request),
            'caller_country'        => $this->callerCountry($request),
            'caller_country_code'   => $this->callerCountryCode($request),
            'caller_number'         => $this->callerPhoneNumber($request),
            'dialed_country_code'   => $this->dialedCountryCode($request),
            'dialed_number'         => $this->dialedPhoneNumber($request),
        ], $with);
    }
}
