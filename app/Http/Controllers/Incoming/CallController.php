<?php

namespace App\Http\Controllers\Incoming;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Events\IncomingCallEvent;
use App\Models\PhoneNumber;
use App\Models\PhoneNumberPool;
use App\Models\AudioClip;
use Twilio\TwiML;
use Twilio\TwiML\VoiceResponse;
use Validator;

class CallController extends Controller
{
    /**
     * Entry point for all new incoming calls
     * 
     */
    public function handleCall(Request $request)
    {
        $rules = [
            'CallSid'       => 'required|max:64',
            'Called'        => 'required|max:16',
            'Caller'        => 'required|max:16',
            'CallStatus'    => 'required'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        event(new IncomingCallEvent($request->input()));

        $response = new VoiceResponse();

        //  Find out how to handle this call
        $dialedCountryCode = PhoneNumber::countryCode($request->Called);
        $dialedNumber      = PhoneNumber::phone($request->Called);

        $phoneNumber = PhoneNumber::where('country_code', $dialedCountryCode)
                                  ->where('number', $dialedNumber)
                                  ->first();
        
        if( ! $phoneNumber ){
            //  Who are you?!?!
            $response->hangup();

            return Response::xmlResponse($response);
        }

        $handler = $phoneNumber->phone_number_pool_id 
                    ? PhoneNumberPool::find($phoneNumber->phone_number_pool_id ) 
                    : $phoneNumber;

        if( ! $handler ){
            //  Phone pool was deleted?
            $response->hangup();

            return Response::xmlResponse($response);
        }

        $dialConfig = [
            'answerOnBridge' => 'true',
            'record'         => $handler->recordingEnabled() ? 'true' : 'false'
        ];

        //  Play audio
        if( $audioClipId = $handler->audioClipId() ){
            $audioClip = AudioClip::find($audioClipId);
            if( $audioClip )
                $response->play($audioClip->getURL());
        }

        //  Set up whisper
        $numberConfig = [];
        if( $handler->whisper_message ){
            $numberConfig['url'] = route('whisper', [
                'whisper_message'  => $handler->whisper_message,
                'whisper_language' => $handler->whisper_language,
                'whisper_voice'    => $handler->whisper_voice
            ]);
        }        

        $dialCommand = $response->dial(null, $dialConfig);

        $dialCommand->number($handler->forwardToPhoneNumber(), $numberConfig);

        return Response::xmlResponse($response);
    }

    /*
     * Handle response for a recorded call
     * 
     
    public function handleRecordedCall(Request $request)
    {
        $rules = [
            'forward_to_phone' => 'required|max:16',
            'audio_clip_url'   => 'url'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $response = new VoiceResponse();
        if( $request->audio_clip_url )
            $response->play(urldecode($request->audio_clip_url));

        $config = [];
        if( $request->whisper_message ){
            $config['url'] = route('whisper', [
                'message'  => $request->whisper_message,
                'language' => $request->whisper_language,
                'voice'    => $request->whisper_voice
            ]);
        }

        $dialCommand = $response->dial();
        $dialCommand->number($request->forward_to_phone, $config);

        return Response::xmlResponse($response);
    }

    */
    public function handleCallStatusChanged(Request $request)
    {
        $rules = [
            'CallSid'       => 'required|max:64',
            'Called'        => 'required|max:16',
            'Caller'        => 'required|max:16',
            'CallStatus'    => 'required'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        event(new IncomingCallEvent($request->input()));
    }

    /**
     * Whisper message
     * 
     */
    public function whisper(Request $request)
    {
        $config = config('services.twilio');

        $rules = [
            'whisper_message'   => 'max:255',
            'whisper_language'  => 'in:' . implode(',', array_keys($config['languages'])),
            'whisper_voice'     => 'in:' . implode(',', array_keys($config['voices'])),
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        $response = new VoiceResponse();

        $config = [];
        if( $request->language )
            $config['language'] = $request->whisper_language;

        if( $request->voice )
            $config['voice'] = $request->whisper_voice;
            

        $response->say($request->whisper_message, $config);

        return Response::xmlResponse($response);
    }
}
