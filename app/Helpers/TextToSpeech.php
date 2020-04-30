<?php
namespace App\Helpers;

use Exception;

class TextToSpeech
{
    protected $client;

    public function __construct($client)
    {
        $this->client = $client; 
    }   

    public function say($language, $voice, $text)
    {
        try{
            $result = $this->client->synthesizeSpeech([
                'Engine'        => 'standard',
                'LanguageCode'  => $language,
                'VoiceId'       => $voice,
                'OutputFormat'  => 'mp3',
                'Text'          => $text,
                'TextType'      => 'text'
            ]);

            return $result['AudioStream']->getContents();
        }catch(Exception $e){
            exit($e->getMessage());
            return null;
        }
    }
}