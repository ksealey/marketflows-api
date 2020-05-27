<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\PhoneNumberPool;
use App\Helpers\PhoneNumberManager;
use App;

class DeletedPhoneNumberPoolJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $phoneNumberPool;
    public $numberManager;
    public $user;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($phoneNumberPool, $user)
    {
        $this->phoneNumberPool = $phoneNumberPool;
        $this->user = $user;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->numberManager = App::make(PhoneNumberManager::class);

        foreach( $this->phoneNumberPool->phone_numbers as $phoneNumber ){
            //  Release the number if it will be renewed with 5 days 
            //  or it gets more than 10 calls per day over the last 3 days
            $callsOverThreeDays = $phoneNumber->callsForPreviousDays(3);

            if( $phoneNumber->willRenewBeforeDays(5) || $callsOverThreeDays >= 30 ){
                $this->numberManager
                     ->releaseNumber($phoneNumber);
            }else{
                $this->numberManager
                    ->bankNumber($phoneNumber, $callsOverThreeDays <= 9 ? true : false); // Make avaiable now if it gets less than or equal to 3 calls per day
            }
            
            $phoneNumber->deleted_by = $this->user->id;
            $phoneNumber->deleted_at = now();
            $phoneNumber->save();
        }
    }
}
