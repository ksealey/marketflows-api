<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Company\CallRecording;

class BatchDeleteCallRecordingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $companyId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($companyId)
    {
        $this->companyId = $companyId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        CallRecording::withTrashed()
                    ->whereIn('call_id', function($q){
                        $q->select('id')
                          ->from('calls')
                          ->where('company_id', $this->companyId); 
                    })
                    ->get()
                    ->each(function($callRecording){
                        $callRecording->deleteRemoteResource();
                    });  
    }
}
