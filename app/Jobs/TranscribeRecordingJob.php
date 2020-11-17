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
       $this->company   = $company;
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

        $transcriptionPath = 'accounts/' . $company->account_id . '/companies/' . $company->id . '/transcriptions';

        //  JSON Version
        Storage::put($transcriptionPath . '/Transcription-' . $recording->call_id . '.json', $transcriber->transformContent($content), [
            'visibility'               => 'public',
            'AccessControlAllowOrigin' => '*'
        ]);

        //  Text Version
        $textPath = $transcriptionPath . '/Transcription-' . $recording->call_id . '.txt';
        Storage::put($textPath, $transcriber->transformContent($content, true), [
            'visibility'               => 'public',
            'AccessControlAllowOrigin' => '*'
        ]);
        
        $recording->transcription_path = $textPath;
        $recording->save();

        $transcriber->deleteTranscription($jobId);
    }
}
