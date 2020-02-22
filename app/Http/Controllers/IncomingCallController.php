<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumberPool;
use App\Models\Company\AudioClip;
use App\Models\BlockedPhoneNumber;
use App\Models\Company\PhoneNumber\Call;
use App\Models\Company\PhoneNumber\CallRecording;
use App\Events\IncomingCallEvent;
use App\Events\IncomingCallUpdatedEvent;
use App\Models\Events\Session;
use App\Models\Events\SessionEvent;
use App\Helpers\InsightsClient;
use Twilio\TwiML\VoiceResponse;
use Twilio\Rest\Client as TwilioClient;
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
            'ToCity'        => 'max:255',
            'ToState'       => 'max:255',
            'ToZip'         => 'max:16',
            'ToCountry'     => 'max:255',
            'From'          => 'required|max:16',
            'FromCity'      => 'max:255',
            'FromState'     => 'max:255',
            'FromZip'       => 'max:16',
            'FromCountry'   => 'max:255'
        ];

        $validator = Validator::make($request->input(), $rules);
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
        
        $phoneNumber = $query->first();
        if( ! $phoneNumber ){
            //  Who are you?!?!
            $response->hangup();

            return Response::xmlResponse($response);
        }

        $config     = null;
        $pool       = null;
        $session    = null;

        if( $phoneNumber->phone_number_pool_id ){
            $pool = PhoneNumberPool::find($phoneNumber->phone_number_pool_id);
            
            $config = $pool->phone_number_config;

            //
            //  Fetch from reporting
            //
            
            //  Get the session events associated with this number, ordering by events
            //$sessionEvents = SessionEvent::

        }else{
            $config     = $phoneNumber->phone_number_config;

            $source     = $phoneNumber->source;
            $medium     = $phoneNumber->medium;
            $content    = $phoneNumber->content;
            $campaign   = $phoneNumber->campaign;
        }

        $company = $phoneNumber->company;

        //
        //  Handle blocked calls
        //
        $bnQuery = BlockedPhoneNumber::where('account_id', $company->account_id)
                                     ->where('number', $dialedNumber);

        if( $dialedCountryCode )
            $bnQuery->where('country_code', $dialedCountryCode);

        $bnQuery->orderBy('created_at', 'desc');

        $blockedNumbers = $bnQuery->get();
        foreach( $blockedNumbers as $blockedNumber ){
            //  If it's on the whole account, or the number they called is attached to a company that blocked it
            if( ! $blockedNumber->company_id || $blockedNumber->company_id == $phoneNumber->company_id ){
                $blockedNumber->calls++;

                $blockedNumber->save();

                $response->hangup();

                return Response::xmlResponse($response);
            }
        }
        
        //  Default name
        $callerFirstName = $request->FromCity  ?: null;
        $callerLastName  = $request->FromState ?: null;

        //  Perform Lookups
        if( $config->caller_id_enabled_at ){
            try{
                $twilio = new TwilioClient(env('TWILIO_SID'), env('TWILIO_TOKEN'));
                $caller = $twilio->lookups->v1
                                ->phoneNumbers($request->from)
                                ->fetch([
                                    'type' => ['caller-name']
                                ]);
                $callerFirstName = $caller->firstName ?: $callerFirstName;
                $callerLastName  = $caller->lastName  ?: $callerLastName;
            }catch(Exception $_){}
        }

        //  Log call
        $call = Call::create([
            'account_id'                => $company->account_id,
            'company_id'                => $company->id,
            'phone_number_id'           => $phoneNumber->id,

            'phone_number_pool_id'      => $pool ? $pool->id : null,
            'session_id'                => $session ? $session->id : null,

            'caller_id_enabled'         => $config->caller_id_enabled_at ? true : false,
            'recording_enabled'         => $config->recording_enabled_at ? true : false,
            'forwarded_to'              => $config->forwardToPhoneNumber(),
            
            'external_id'               => $request->CallSid,
            'direction'                 => ucfirst($request->Direction),
            'status'                    => 'In Progress',
            
            'caller_first_name'         => substr(ucfirst($callerFirstName), 0, 64),
            'caller_last_name'          => substr(ucfirst($callerLastName), 0, 64),
            'caller_country_code'       => PhoneNumber::countryCode($request->From) ?: null,
            'caller_number'             => PhoneNumber::number($request->From),
            'caller_city'               => $request->FromCity ? substr($request->FromCity, 0, 64) : null,
            'caller_state'              => $request->FromState ? substr($request->FromState, 0, 64) : null,
            'caller_zip'                => $request->FromZip ? substr($request->FromZip, 0, 64) : null,
            'caller_country'            => $request->FromCountry ? substr($request->FromCountry, 0, 64) : null,
            
            'dialed_country_code'       => PhoneNumber::countryCode($request->To) ?: null,
            'dialed_number'             => PhoneNumber::number($request->To),
            'dialed_city'               => $request->ToCity ? substr($request->ToCity, 0, 64) : null,
            'dialed_state'              => $request->ToState ? substr($request->ToState, 0, 64) : null,
            'dialed_zip'                => $request->ToZip ? substr($request->ToZip, 0, 64) : null,
            'dialed_country'            => $request->ToCountry ? substr($request->ToCountry, 0, 64) : null,
        
            'source'                    => $source,
            'medium'                    => $medium,
            'content'                   => $content,
            'campaign'                  => $campaign
        ]);

        //  Let the rest of the system know it happened
        event(new IncomingCallEvent($call, $session));

        $dialConfig = ['answerOnBridge' => 'true'];

        //  Handle recording
        if( $config->recording_enabled_at ){
            $dialConfig['record']                       = 'record-from-ringing';
            $dialConfig['recordingStatusCallback']      = route('incoming-call-recording-available');
            $dialConfig['recordingStatusCallbackEvent'] = 'completed';
        }else{
            $dialConfig['record'] = 'do-not-record';
        }
        
        //  Handle greeting
        if( $config->greeting_audio_clip_id ){
            $audioClip = AudioClip::find($config->greeting_audio_clip_id);
            if( $audioClip )
                $response->play($audioClip->getURL());
        }elseif( $config->greeting_message ){
            $response->say($config->greetingMessage($call), [
                'language' => $company->tts_language,
                'voice'    => $company->tts_voice
            ]);
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

        if( ! $call ){
            return response([
                'error' => 'Not found',
            ], 404);
        }

        //  Update call
        $call->status   = $request->CallStatus;
        $call->duration = $request->CallDuration;
        $call->save();

        //  And let the rest of the system know it happened
        event(new IncomingCallUpdatedEvent($call));
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
        $config = config('services.twilio');

        $rules = [
            'whisper_language'  => 'in:' . implode(',', array_keys($config['languages'])),
            'whisper_voice'     => 'in:' . implode(',', array_keys($config['voices'])),
            'whisper_message'   => 'max:255',
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        $response = new VoiceResponse();

        $config = [];
        if( $request->whisper_language )
            $config['language'] = $request->whisper_language;

        if( $request->whisper_voice )
            $config['voice'] = $request->whisper_voice;

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
    
        DB::beginTransaction();

        $call = Call::where('external_id', $request->CallSid)->first();
        if( ! $call ){
            return response([
                'error' => 'Not found'
            ], 404);
        }

        $storagePath = CallRecording::moveRecording(
            $request->RecordingUrl, 
            $request->RecordingSid, 
            $call->phoneNumber->company
        );

        //  Log record
        CallRecording::create([
            'call_id'       => $call->id,
            'external_id'   => $request->RecordingSid,
            'path'          => $storagePath,
            'duration'      => intval($request->RecordingDuration)
        ]);
    }
}
