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

    public function transcribe($recording, $asText = false, $languageCode = 'en-US')
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

        //  Delete original
        $this->deleteTranscription($jobId);

        return $this->transformContent($content, $asText);
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
        $response = $this->httpClient->request('GET', $url, [
            'connect_timeout' => 120
        ]);

        return json_decode($response->getBody());
    }

    public function transformContent($content, $asText = false)
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

        if( $asText ){
            $txt = '';

            //  Merge into one big dataset
            $allItems = [];
            foreach($transcript['channels'] as $channel){
                $items = array_map(function($item) use($channel){
                    $item['speaker_label'] = $channel['label'];
                    $item['speaker_name']  = $channel['label'] == 'ch_0' ? 'Caller' : 'Agent';
                    return $item;
                }, $channel['content']);
                $allItems = array_merge($allItems, $items);
            }

            //  Order
            usort($allItems, function($a, $b){
                return $a['start_time'] < $b['start_time'] ? -1 : 1;
            });

            //  Transform to text file
            $lastSpeaker = null;
            foreach( $allItems as $idx => $item ){
                if( $item['speaker_label'] != $lastSpeaker ){
                    $txt .= $item['speaker_name'] . ": " . $item['start_time'] . "s\n";
                }
                
                $txt .= $item['text'];
                if( isset($allItems[$idx+1]) ){
                    $txt .= "\n";
                    if( $item['speaker_label'] != $allItems[$idx+1]['speaker_label'] )
                        $txt .= "\n";
                }
                $lastSpeaker = $item['speaker_label'];
            }
            $transcript = $txt;
        }else{
            $transcript = json_encode($transcript);
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