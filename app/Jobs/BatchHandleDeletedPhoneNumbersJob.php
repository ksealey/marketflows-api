<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Company\PhoneNumber;
use App\Helpers\PhoneNumberManager;
use App;

class BatchHandleDeletedPhoneNumbersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $companyId;

    public $numberManager;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($companyId)
    {
        $this->companyId = $companyId;

        $this->numberManager = App::make(PhoneNumberManager::class);
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
                        if( $phoneNumber->willRenewBeforeDays(5) ){
                            $this->numberManager
                                 ->releaseNumber($phoneNumber);

                            usleep(250); // Only allow 4 api requests per second
                        }else{
                            $this->numberManager
                                 ->bankNumber($phoneNumber);
                        }
                           
                    });  
    }
}
