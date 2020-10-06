<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Company\PhoneNumber;
use App\Services\PhoneNumberService;
use App;

class DeleteKeywordTrackingPoolJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $keywordTrackingPool;
    public $releaseNumbers;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user, $keywordTrackingPool, $releaseNumbers)
    {
        $this->user                = $user;
        $this->keywordTrackingPool = $keywordTrackingPool;
        $this->releaseNumbers      = $releaseNumbers;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $phoneNumbers = $this->keywordTrackingPool->phone_numbers;

        PhoneNumber::where('keyword_tracking_pool_id', $this->keywordTrackingPool->id)
                   ->update([ 'keyword_tracking_pool_id' => null ]);

        if( ! $this->releaseNumbers ) return;
        
        $numberService = App::make(PhoneNumberService::class);
        foreach($phoneNumbers as $idx => $phoneNumber){
            $numberService->releaseNumber($phoneNumber);
            $phoneNumber->deleted_by = $this->user->id;
            $phoneNumber->deleted_at = now();
            $phoneNumber->save();
        }
    }
}
