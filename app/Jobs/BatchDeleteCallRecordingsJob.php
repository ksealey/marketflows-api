<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Company\CallRecording;
use App\Models\User;
use App\Models\Company;
use Storage;

class BatchDeleteCallRecordingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $company;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user, Company $company)
    {
        $this->user    = $user;
        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        //  Fetch all recordings
        //
        $recordings = CallRecording::whereIn('call_id', function($q){
                        $q->select('id')
                          ->from('calls')
                          ->where('company_id', $this->company->id)
                          ->whereNull('deleted_at'); 
                    })
                    ->get();

        if( ! count($recordings) ) return;

        //  Remove remote files
        $recordings->each(function($recording){
            Storage::delete($recording->path);
            if( $recording->transcription_path ){
                Storage::delete($recording->transcription_path);
            }
        });

        //  Delete records from database
        $recordingIds = array_column($recordings->toArray(), 'id');
        CallRecording::whereIn('id', $recordingIds)
                      ->delete(); 

    }
}
