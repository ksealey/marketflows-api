<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use \App\Services\PhoneNumberService;
use \App\Models\Account;
use \App\Models\Company\PhoneNumber;
use App;

class ReleaseAccountNumbersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $account;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $numberService = App::make(PhoneNumberService::class);
      
        PhoneNumber::where('account_id', $this->account->id)
                    ->get()
                    ->each(function($phoneNumber) use($numberService){
                        $numberService->releaseNumber($phoneNumber);
                        $phoneNumber->delete();
                        usleep(250); // Limit to 4 requests per second
                    }); 
    }
}
