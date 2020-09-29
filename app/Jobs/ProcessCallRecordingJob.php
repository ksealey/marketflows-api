<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Company\Call;
use App\Models\Company\CallRecording;
use App\Services\TranscribeService;
use Twilio\Rest\Client as TwilioClient;
use App;
use Exception;
use Storage;

class ProcessCallRecordingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $call;
    public $recordingURL;
    public $recordingSid;
    public $recordingDuration;

    public $tries = 3;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Call $call, $recordingURL, $recordingSid, $recordingDuration)
    {
        $this->call                 = $call;
        $this->recordingURL         = $recordingURL;
        $this->recordingSid         = $recordingSid;
        $this->recordingDuration    = $recordingDuration; 
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        // Move recording to storage and remove from Twilio
        //

        //  Download recording
        $httpClient = App::make('HTTPClient');
        $response   = $httpClient->request('GET', $this->recordingURL . '.mp3');
        $content    = (string)$response->getBody();

        if( ! $content ) throw new Exception('Unable to download file ' . $this->recordingURL);

        //  Store remotely
        $call        = $this->call;
        $storagePath = 'accounts/' . $call->account_id . '/companies/' . $call->company_id . '/recordings/Call-' . $call->id . '.mp3';
        Storage::put($storagePath, $content, 'public');

        //  Create record
        $recording = CallRecording::create([
            'call_id'       => $call->id,
            'external_id'   => $this->recordingSid,
            'path'          => $storagePath,
            'duration'      => intval($this->recordingDuration),
            'file_size'     => strlen($content),
            'transcription_path' => '/'
        ]);

        //  Delete original
        $twilio = App::make(TwilioClient::class);
        $twilio->recordings($this->recordingSid)
               ->delete();
        
        //  Transcribe if enabled
        if( $call->transcription_enabled ){
            $transcriber                   = App::make(TranscribeService::class);
            $recording->transcription_path = $transcriber->transcribe($recording);
            $recording->save();
        }
    }
}
