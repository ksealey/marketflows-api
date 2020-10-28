<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Company;
use App\Models\Company\CallRecording;
use App\Services\TranscribeService;
use App;
use Storage;
use Exception;

class TranscribeRecordingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 3;
    public $timeout = 900; // 15 minutes

    public $company;
    public $recording;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Company $company, CallRecording $recording)
    {
       $this->company = $company;
       $this->recording = $recording; 
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $company        = $this->company;
        $recording      = $this->recording;

        $language       = config('services.transcribe.languages')[$company->tts_language] ?? 'en-US';
        $transcriber    = App::make(TranscribeService::class);
        
        $jobId   = $transcriber->startTranscription($recording, $language);
        $fileUrl = $transcriber->waitForUrl($jobId);
        $content = $transcriber->downloadFromUrl($fileUrl); 
        if( ! $content ) throw new Exception('Unable to download transcript for job ' . $jobId);

        $recording->transcription_path = str_replace('recordings/Call-' . $recording->call_id . '.mp3', 'transcriptions/Transcription-' . $recording->call_id . '.json', $recording->path);
        Storage::put($recording->transcription_path, json_encode($transcriber->transformContent($content)), [
            'visibility'               => 'public',
            'AccessControlAllowOrigin' => '*'
        ]);
        
        $recording->save();

        $transcriber->deleteTranscription($jobId);
    }
}
