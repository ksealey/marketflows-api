<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Account;
use App\Models\AccountBlockedPhoneNumber;
use App\Models\AccountBlockedPhoneNumber\AccountBlockedCall;
use App\Models\BankedPhoneNumber;
use App\Models\Company\Contact;
use App\Models\Company\PhoneNumberConfig;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumberPool;
use App\Models\Company\AudioClip;
use App\Models\Company\BlockedPhoneNumber;
use App\Models\Company\BlockedPhoneNumber\BlockedCall;
use App\Models\Company\Call;
use App\Models\Company\CallRecording;
use App\Models\Events\Session;
use App\Models\Events\SessionEvent;
use Twilio\TwiML\VoiceResponse;
use Twilio\Rest\Client as Twilio;
use \App\Helpers\PhoneNumberManager;
use Validator;
use Storage;
use Exception;
use DB;

class IncomingCallController extends Controller
{
    protected $numberManager;

    public function __construct(PhoneNumberManager $numberManager)
    {
        $this->numberManager = $numberManager;
    }

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
        
        //  If we don't recognize this call
        $phoneNumber = $query->first();
        if( ! $phoneNumber ){
            $query = BankedPhoneNumber::where('number', $dialedNumber); 
            if( $dialedCountryCode )
                $query->where('country_code', $dialedCountryCode);
            
            $bankedNumber = $query->first();
            if( $bankedNumber ){
                $bankedNumber->calls++;
                $bankedNumber->save();
            }

            $response->reject();

            return Response::xmlResponse($response);
        }

        //  
        //  Reject call if number is disabled
        //
        if( $phoneNumber->disabled_at ){
            $response->reject();

            return Response::xmlResponse($response);
        }

        //
        //  Log and Reject call if blocked
        //
        $callerCountryCode = PhoneNumber::countryCode($request->From);
        $callerNumber      = PhoneNumber::number($request->From);

        //  Company level
        $query = BlockedPhoneNumber::where('company_id', $phoneNumber->company_id)
                                    ->where('number', $callerNumber);
        
        if( $callerCountryCode )
            $query->where('country_code', $callerCountryCode);
        
        $blockedPhoneNumber = $query->first();

        if( $blockedPhoneNumber ){
            BlockedCall::create([
               'blocked_phone_number_id' => $blockedPhoneNumber->id,
               'phone_number_id'         => $phoneNumber->id,
               'created_at'              => now()
            ]);

            $response->reject();

            return Response::xmlResponse($response);
        }

        //
        //  Account level number block
        //
        $query = AccountBlockedPhoneNumber::where('account_id', $phoneNumber->account_id)
                                           ->where('number', $callerNumber);
        if( $callerCountryCode )
            $query->where('country_code', $callerCountryCode);

        $accountBlockedPhoneNumber = $query->first();
        if( $accountBlockedPhoneNumber ){
            AccountBlockedCall::create([
               'account_blocked_phone_number_id' => $accountBlockedPhoneNumber->id,
               'phone_number_id'                 => $phoneNumber->id,
               'created_at'                      => now()
            ]);

            $response->reject();

            return Response::xmlResponse($response);
        }

        //
        //  Look for contact and create if not exists
        //
        $cleanFullPhone = $callerCountryCode . $callerNumber;
        $contact = Contact::where('company_id', $phoneNumber->company_id)
                          ->where('phone', $cleanFullPhone)
                          ->first();
        if( ! $contact ){
            $firstCall  = true;
            $callerName = $request->CallerName ?: null;
            if( ! $callerName ){
                $callerName  = trim(strtolower($request->FromCity  ?: '') . ' ' . ($request->FromState ?: ''));
            }
            
            $callerName       = str_replace(',', ' ', ($callerName ? $callerName : 'Unknown Caller'));
            $callerNamePieces = explode(' ', $callerName);
    
            $contact = Contact::create([
                'account_id'    => $phoneNumber->account_id,
                'company_id'    => $phoneNumber->company_id,
                'first_name'    => $callerNamePieces[0],
                'last_name'     => !empty($callerNamePieces[1]) ? $callerNamePieces[1] : '',
                'email'         => null,
                'phone'         => $cleanFullPhone,
                'city'          => $request->FromCity ? substr($request->FromCity, 0, 64) : null,
                'state'         => $request->FromState ? substr($request->FromState, 0, 64) : null,
                'zip'           => $request->FromZip ? substr($request->FromZip, 0, 64) : null,
                'country'       => $request->FromCountry ? substr($request->FromCountry, 0, 64) : null
            ]);
        }else{
            $firstCall = false;
        }

        //
        //  Determine how to route this call and capture sourcing data
        //  
        $trackingSessionId  = null;

        $keyword  = null;
        $source   = null;
        $medium   = null;
        $campaign = null;
        $content  = null;

        if( $phoneNumber->phone_number_pool_id ){
            $pool        = $phoneNumber->phone_number_pool;
            $config      = $pool->phone_number_config;
            $session     = $pool->getSessionData($request->From, $phoneNumber);

            if( $session ){
                $trackingSessionId = $session->id;

                $source     = $session->source   ?: null;
                $medium     = $session->medium   ?: null;
                $campaign   = $session->campaign ?: null;
                $content    = $session->content  ?: null;
                $keyword    = $session->keyword  ?: null;
            }
            
        }else{
            $config = $phoneNumber->phone_number_config;

            $source     = $phoneNumber->source;
            $medium     = $phoneNumber->medium ?: null;
            $campaign   = $phoneNumber->campaign ?: null;
            $content    = $phoneNumber->content ?: null;
        }

        //
        //  Log call
        //
        $call = Call::create([
            'account_id'                => $phoneNumber->account_id,
            'company_id'                => $phoneNumber->company_id,
            'phone_number_id'           => $phoneNumber->id,
            'contact_id'                => $contact->id,
            'type'                      => $phoneNumber->type,
            'category'                  => $phoneNumber->category,
            'sub_category'              => $phoneNumber->sub_category,
            'phone_number_pool_id'      => $phoneNumber->phone_number_pool_id ?: null,
            'first_call'                => $firstCall,
            'tracking_session_id'       => $trackingSessionId,
            'external_id'               => $request->CallSid,
            'direction'                 => substr(ucfirst($request->Direction), 0, 16),
            'status'                    => substr(ucfirst($request->CallStatus), 0, 64),
            'source'                    => $source,
            'medium'                    => $medium,
            'content'                   => $content,
            'campaign'                  => $campaign,
            'keyword'                   => $keyword,
            'recording_enabled'         => $config->recording_enabled,
            'forwarded_to'              => $config->forwardToPhoneNumber(),
            'created_at'                => now()->format('Y-m-d H:i:s.u'),
            'updated_at'                => now()->format('Y-m-d H:i:s.u')
        ]);

        //
        //  Handle recording, greeting, keypad entry, forwarding, and whisper message
        //
        $company    = $phoneNumber->company;
        $dialConfig = ['answerOnBridge' => 'true'];

        //  Handle recording
        if( $config->recording_enabled ){
            $dialConfig['record']                       = 'record-from-ringing-dual';
            $dialConfig['recordingStatusCallback']      = route('incoming-call-recording-available');
            $dialConfig['recordingStatusCallbackEvent'] = 'completed';
        }else{
            $dialConfig['record'] = 'do-not-record';
        }
        
        //  Handle greeting
        if( $config->greeting_audio_clip_id ){
            $audioClip = AudioClip::find($config->greeting_audio_clip_id);
            if( $audioClip )
                $response->play($audioClip->url);
        }elseif( $config->greeting_message ){
            $response->say($config->greetingMessage($call), [
                'language' => $company->tts_language,
                'voice'    => 'Polly.' . $company->tts_voice
            ]);
        }

        //  Handle keypad entry, sending response now
        if( $config->keypress_enabled){
            $gather = $response->gather(['numDigits' => 1]);
            if( $config->keypress_audio_clip_id ){
                $audioClip = AudioClip::find($config->keypress_audio_clip_id);
                if( $audioClip )
                    $response->play($audioClip->url);
            }else{
                $gather->say($config->keypressMessage($call), [
                    'language' => $company->tts_language,
                    'voice'    => 'Polly.' . $company->tts_voice
                ]);
            }

            $response->redirect(route('incoming-call-collect', [
                'call_id'                => $call->id,
                'phone_number_config_id' => $config->id,
                'attempts'               => 1
            ]));

            return Response::xmlResponse($response);
        }

        //  Handle whisper message
        $numberConfig = [];
        if( $config->whisper_message ){
            $numberConfig['url'] = route('incoming-call-whisper', [
                'whisper_message'  => $config->whisperMessage($call),
                'whisper_language' => $company->tts_language,
                'whisper_voice'    => $company->tts_voice
            ]);

            $numberConfig['method'] = 'GET';
        }

        $dialCommand = $response->dial(null, $dialConfig);

        $dialCommand->number($config->forwardToPhoneNumber(), $numberConfig);

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
         
        //  ... 
        
        $call->save();
        //  Let the rest of the system know it happened
        //
        //
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

        CallRecording::moveRecording(
            $request->RecordingUrl, 
            $request->RecordingSid,
            $request->RecordingDuration,
            $call
        );
    }

    public function handleCollect(Request $request)
    {
        $attempts = intval($request->attempts);
        $digit    = intval($request->Digits);
        $config   = PhoneNumberConfig::find($request->phone_number_config_id); 
        $call     = Call::find($request->call_id);
        $company  = $call->company;

        $response = new VoiceResponse();

        //  No keypad entry OR wrong key
        if( (! $digit && $digit !== 0) || $digit != $config->keyress_key ){
            if( $attempts + 1 > $config->keypress_attempts ){
                //  Reject the call for too many attempts
                $response->reject();
                return Response::xmlResponse($response);
            }

            //  Allow another attempt
            $gather = $response->gather(['numDigits' => 1]);
            if( $config->keypress_audio_clip_id ){
                $audioClip = AudioClip::find($config->keypress_audio_clip_id);
                if( $audioClip )
                    $response->play($audioClip->url);
            }else{
                $gather->say($config->keypressMessage($call), [
                    'language' => $company->tts_language,
                    'voice'    => 'Polly.' . $company->tts_voice
                ]);
            }

            $response->redirect(route('incoming-call-collect', [
                'phone_number_config_id' => $request->phone_number_config_id,
                'call_id'                => $request->call_id,
                'attempts'               => $attempts + 1
            ]));

            return Response::xmlResponse($response);
        }

        //  Valid input
        $numberConfig = [];
        if( $config->whisper_message ){
            $numberConfig['url'] = route('incoming-call-whisper', [
                'whisper_message'  => $config->whisperMessage($call),
                'whisper_language' => $company->tts_language,
                'whisper_voice'    => $company->tts_voice
            ]);

            $numberConfig['method'] = 'GET';
        }

        $dialCommand = $response->dial(null, $dialConfig);

        $dialCommand->number($config->forwardToPhoneNumber(), $numberConfig);

        return Response::xmlResponse($response);
    }
}
