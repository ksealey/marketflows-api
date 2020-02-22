<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Validator;
use AWS;

class TextToSpeechController extends Controller
{
    public function say(Request $request)
    {
        $settings = config('services.twilio');

        $rules = [
            'text'      => 'required|max:128',
            'language'  => 'in:' . implode(',', array_keys($settings['languages'])),
            'voice'     => 'in:' . implode(',', array_keys($settings['voices-aws-map']))
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() )
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);

        $language = $request->language ?? 'en-US';
        $voice    = $request->voice ?? 'Amy';

        $client = AWS::createClient('polly');

        try{
            $result = $client->synthesizeSpeech([
                'Engine'        => 'standard',
                'LanguageCode'  => $language,
                'VoiceId'       => $voice,
                'OutputFormat'  => 'mp3',
                'Text'          => $request->text,
                'TextType'      => 'text'
            ]);

            exit($result['AudioStream']->getContents());
        }catch(Exception $e){
            return response([
                'error' => 'Unable to generate audio.'
            ], 500);
        }
    }
}
