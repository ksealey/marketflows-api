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
        //  Delete file
        //
        CallRecording::whereIn('call_id', function($q){
                        $q->select('id')
                          ->from('calls')
                          ->where('company_id', $this->company->id)
                          ->whereNull('deleted_at'); 
                    })
                    ->get()
                    ->each(function($callRecording){
                        $callRecording->deleteRemoteResource();
                    });  
        //
        //  Delete from database
        //
        CallRecording::whereIn('call_id', function($q){
                        $q->select('id')
                          ->from('calls')
                          ->where('company_id', $this->company->id)
                          ->whereNull('deleted_at'); 
                    })
                    ->update([ 'deleted_at' => now() ]);  

    }
}
