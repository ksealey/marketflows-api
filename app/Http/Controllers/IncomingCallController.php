<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumberPool;
use App\Models\Company\AudioClip;
use App\Models\Company\PhoneNumber\Call;
use App\Models\Company\PhoneNumber\CallRecording;
use App\Events\IncomingCallEvent;
use App\Events\IncomingCallUpdatedEvent;
use App\Helpers\InsightsClient;
use Twilio\TwiML\VoiceResponse;
use Validator;
use Storage;
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

        $pool = null;
        if( $phoneNumber->phone_number_pool_id )
            $pool = PhoneNumberPool::find($phoneNumber->phone_number_pool_id);
    

        //  Try to get the config
        $phoneNumberConfig = $phoneNumber->getPhoneNumberConfig();
        
        //  Log call
        $call = Call::create([
            'phone_number_id'       => $phoneNumber->id,
            'phone_number_pool_id'  => $pool ? $pool->id : null,
            'phone_number_config_id'=> $phoneNumberConfig->id,
            'external_id'           => $request->CallSid,
            'direction'             => $request->Direction,
            'status'                => $request->CallStatus,
            'from_country_code'     => PhoneNumber::countryCode($request->From) ?: null,
            'from_number'           => PhoneNumber::number($request->From),
            'from_city'             => $request->FromCity ?: null,
            'from_state'            => $request->FromState ?: null,
            'from_zip'              => $request->FromZip ?: null,
            'from_country'          => $request->FromCountry ?: null,
            'to_country_code'       => PhoneNumber::countryCode($request->To) ?: null,
            'to_number'             => PhoneNumber::number($request->To),
            'to_city'               => $request->ToCity ?: null,
            'to_state'              => $request->ToState ?: null,
            'to_zip'                => $request->ToZip ?: null,
            'to_country'            => $request->ToCountry ?: null,
        ]);

        //  Let the rest of the system know it happened
        event(new IncomingCallEvent($call));
        
        //  For whatever reason we could not find out what action to take   
        if( ! $phoneNumberConfig ){
            $response->hangup();

            return Response::xmlResponse($response);
        }

        $dialConfig = ['answerOnBridge' => 'true'];

        //  Should we record?
        if( $phoneNumberConfig->recordingEnabled() ){
            $dialConfig['record']                       = 'record-from-ringing';
            $dialConfig['recordingStatusCallback']      = route('incoming-call-recording-available');
            $dialConfig['recordingStatusCallbackEvent'] = 'completed';
        }else{
            $dialConfig['record'] = 'do-not-record';
        }
        
        //  Should we play audio?
        if( $audioClipId = $phoneNumberConfig->audioClipId() ){
            $audioClip = AudioClip::find($audioClipId);
            if( $audioClip )
                $response->play($audioClip->getURL());
        }

        //  Should we have a whisper message?
        $numberConfig = [];
        if( $phoneNumberConfig->whisper_message ){
            $numberConfig['url'] = route('incoming-call-whisper', [
                'whisper_message'  => $phoneNumberConfig->whisper_message,
                'whisper_language' => $phoneNumberConfig->whisper_language,
                'whisper_voice'    => $phoneNumberConfig->whisper_voice
            ]);

            $numberConfig['method'] = 'GET';
        } 

        $dialCommand = $response->dial(null, $dialConfig);

        $dialCommand->number($phoneNumberConfig->forwardToPhoneNumber(), $numberConfig);

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
