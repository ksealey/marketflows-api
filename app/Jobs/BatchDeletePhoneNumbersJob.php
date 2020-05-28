<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Company\PhoneNumber;
use App\Helpers\PhoneNumberManager;
use App\Models\User;
use App\Models\Company;
use App;

class BatchDeletePhoneNumbersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $company;
    public $numberManager;

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
        $this->numberManager = App::make(PhoneNumberManager::class);
        
        //
        //  Release or bank remote number
        //
        PhoneNumber::where('company_id', $this->company->id)
                    ->get()
                    ->each(function($phoneNumber){
                        $callsOverThreeDays = $phoneNumber->callsForPreviousDays(3);
                        if( $phoneNumber->willRenewBeforeDays(5) || $callsOverThreeDays >= 30 ){
                            $this->numberManager
                                 ->releaseNumber($phoneNumber);
                            usleep(250); // Limit to 4 requests per second
                        }else{
                            $this->numberManager
                                 ->bankNumber($phoneNumber, $callsOverThreeDays <= 9 ? true : false); // Make avaiable now if it gets less than or equal to 3 calls per day
                        }
                    }); 
        //
        //  Delete from database
        //
        PhoneNumber::where('company_id', $this->company->id)
                    ->update([
                        'deleted_by' => $this->user->id,
                        'deleted_at' => now()
                    ]);
    }
}
