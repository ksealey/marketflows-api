<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\TextToSpeech;
use Validator;
use AWS;

class TextToSpeechController extends Controller
{
    public $tts;

    public function __construct(TextToSpeech $tts)
    {
        $this->tts = $tts;
    }

    public function say(Request $request)
    {
        $config    = config('services.twilio.languages');
        $languages = array_keys($config);
        $language  = $request->language && in_array($request->language, $languages) ? $request->language : 'en-US';
        $voices    = array_keys($config[$language]['voices']); 

        $validator = validator($request->input(), [
            'text'      => 'bail|required',
            'language'  => 'bail|in:' . implode(',', $languages),
            'voice'     => 'bail|in:' . implode(',', $voices),
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $voice = $request->voice    ?? 'Joanna';
        
        return response( 
            $this->tts->say($language, $voice, $request->text) 
        );
    }
}
