<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Validator;
use AWS;

class TextToSpeechController extends Controller
{
    public function say(Request $request)
    {
        $language = $request->language ?? 'en-US';
        $voice    = $request->voice    ?? 'Joanna';

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
