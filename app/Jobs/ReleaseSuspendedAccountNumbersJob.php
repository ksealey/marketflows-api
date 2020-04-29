<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Company\PhoneNumber;
use App\Events\Company\PhoneNumberEvent;
use App\Mail\SuspendedAccountNumbersReleased as SuspendedAccountNumbersReleasedEmail;
use Mail;
use Exception;
use Log;

class ReleaseSuspendedAccountNumbersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $account;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($account)
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
        $account      = $this->account;
        $phoneNumbers = PhoneNumber::where('account_id', $account->id)->get();

        if( ! count($phoneNumbers) ) return;

        PhoneNumber::where('account_id', $account->id)->delete();
    
        event( new PhoneNumberEvent($account, $phoneNumbers, 'delete') );
        
        $users = $account->admin_users;
        foreach( $users as $user ){
            try{
                Mail::to($user)->send(new SuspendedAccountNumbersReleasedEmail($user, $account));
            }catch(Exception $e){
                Log::error($e->getTraceAsString());
            }
        }
    }   
}
