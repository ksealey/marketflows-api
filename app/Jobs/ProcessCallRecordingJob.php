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
        $content    = null;
        $httpClient = App::make('HTTPClient');
        try{
            $response   = $httpClient->request('GET', $this->recordingURL . '.mp3');
            $content    = (string)$response->getBody();
        }catch(\Exception $e){}

        if( ! $content ) throw new Exception('Unable to download file ' . $this->recordingURL);

        //  Store remotely
        $call        = $this->call;
        $storagePath = 'accounts/' . $call->account_id . '/companies/' . $call->company_id . '/recordings/Call-' . $call->id . '.mp3';
        Storage::put($storagePath, $content, 'public');

        //  Create record
        $recording = CallRecording::create([
            'account_id'    => $call->account_id,
            'company_id'    => $call->company_id,
            'call_id'       => $call->id,
            'external_id'   => $this->recordingSid,
            'path'          => $storagePath,
            'duration'      => intval($this->recordingDuration),
            'file_size'     => strlen($content),
            'transcription_path' => '/'
        ]);
        
        //  Transcribe if enabled
        if( $call->transcription_enabled ){
            $company        = $call->company;
            $language       = config('services.transcribe.languages')[$company->tts_language] ?? 'en-US';
            $transcriber    = App::make(TranscribeService::class);

            $jobId   = $transcriber->startTranscription($recording, $language);
            $fileUrl = $transcriber->waitForUrl($jobId);
            $content = $transcriber->downloadFromUrl($fileUrl);
            if( ! $content ) throw new Exception('Unable to download transcript for job ' . $jobId);

            $recording->transcription_path = str_replace('recordings/Call-' . $recording->call_id . '.mp3', 'transcriptions/Transcription-' . $recording->call_id . '.json', $recording->path);
            Storage::put($recording->transcription_path, json_encode($transcriber->transformContent($content)), 'public');

            $transcriber->deleteTranscription($jobId);
            $recording->save();
        }

         //  Delete original
         $twilio = App::make(TwilioClient::class);
         $twilio->recordings($this->recordingSid)
                ->delete();
    }
}
