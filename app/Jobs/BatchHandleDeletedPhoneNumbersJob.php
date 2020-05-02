<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Company\PhoneNumber;

class BatchHandleDeletedPhoneNumbersJob implements ShouldQueue
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
        PhoneNumber::withTrashed()
                    ->where('company_id', $this->companyId)
                    ->get()
                    ->each(function($phoneNumber){
                        $banked = $phoneNumber->bankOrRelease();
                        if( ! $banked )
                            usleep(250); // Only allow 4 api requests per second
                    });  
    }
}
