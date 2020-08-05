<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Company\AudioClip;
use App\Models\User;
use App\Models\Company;

class BatchDeleteAudioJob implements ShouldQueue
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
        AudioClip::where('company_id', $this->company->id)
                 ->get()
                 ->each(function($audioClip){
                    $audioClip->deleteRemoteResource();
                 });  

        //  
        //  Delete in database
        //
        AudioClip::where('company_id', $this->company->id)
                  ->update([
                        'deleted_by' => $this->user->id,
                        'deleted_at' => now()
                  ]);
    }
}
