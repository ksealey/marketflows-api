<?php
namespace App\Services;

use Storage;
use Exception;

class TranscribeService
{
    public $transcriber;
    public $httpClient;

    public function __construct($transcriber, $httpClient)
    {
        $this->transcriber = $transcriber;
        $this->httpClient  = $httpClient;
    }

    public function transcribe($recording, $languageCode = 'en-US')
    {
        //
        //  Transcribe multi-channel
        //
        $jobId   = $this->startTranscription($recording, $languageCode);
        $fileUrl = $this->waitForUrl($jobId);

        //
        //  Download
        //
        $content = $this->downloadFromUrl($fileUrl);

        //
        //  Transform
        //
        if( ! $content )
            throw new Exception('Unable to download transcript for job ' . $jobId);

        $transcript = $this->transformContent($content);

        //
        //  Upload
        //
        $transcriptionPath = str_replace('recordings/Call-' . $recording->call_id . '.mp3', 'transcriptions/Call-' . $recording->call_id . '.json', $recording->path);
        Storage::put($transcriptionPath, json_encode($transcript), 'public');

        //
        //  Delete original
        //
        $this->deleteTranscription($jobId);

        return $transcriptionPath; 
    }

    public function startTranscription($recording, $languageCode)
    {
        $jobId = $recording->id . '-' . date('U');

        $this->transcriber->startTranscriptionJob([
            'LanguageCode' => $languageCode,
            'Media' => [
                'MediaFileUri' => $recording->storage_url,
            ],
            'Settings' => [
                'ChannelIdentification' => true
            ],
            'TranscriptionJobName' => $jobId,
        ]);

        return $jobId;
    }

    public function waitForUrl($jobId)
    {
        $fileUrl = '';

        while(true) {
            $status = $this->transcriber->getTranscriptionJob([
                'TranscriptionJobName' => $jobId
            ]);
         
            if ( $status->get('TranscriptionJob')['TranscriptionJobStatus'] == 'COMPLETED' ){
                $fileUrl = $status->get('TranscriptionJob')['Transcript']['TranscriptFileUri'];
                break;
            }
         
            sleep(5);
        }

        return $fileUrl;
    }

    public function downloadFromUrl($url)
    {
        $response = $this->httpClient->request('GET', $url);

        return json_decode($response->getBody());
    }

    public function transformContent($content)
    {
        $transcript = [
            'channel_labels' => [],
            'channels'       => []
        ];

        foreach( $content->results->channel_labels->channels as $channel ){
            $transcript['channel_labels'][] = $channel->channel_label;
            $channelContent = [];
            foreach( $channel->items as $item ){
                if( ! isset($item->start_time) ) continue;

                $text       = '';
                $confidence = 0;
                $content    = $item->alternatives[0] ?? null;
                if( $content ){
                    $text       = $content->content;
                    $confidence = $content->confidence;
                }
                
                $channelContent[] = [
                    'start_time' => $item->start_time,
                    'end_time'   => $item->end_time,
                    'text'       => $text,
                    'confidence' => $confidence
                ];
            }
            
            $transcript['channels'][] = [
                'label'   => $channel->channel_label,
                'content' => $channelContent
            ];
        }

        return $transcript;
    }

    public function deleteTranscription($jobId)
    {
        $this->transcriber->deleteTranscriptionJob([
            'TranscriptionJobName' => $jobId
        ]);
    }
}