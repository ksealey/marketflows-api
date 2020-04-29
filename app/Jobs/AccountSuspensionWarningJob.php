<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Mail\AccountSuspensionWarning as AccountSuspensionWarningEmail;
use DateTime;
use \Carbon\Carbon;
use Mail;
use Log;
use Exception;

class AccountSuspensionWarningJob implements ShouldQueue
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
        $account     = $this->account;
        $suspendedAt = new Carbon($account->suspended_at);
        $warningAt   = new Carbon($account->suspension_warning_at);

        $diff           = $suspendedAt->diff($warningAt);
        $newWarningDate = null; 
        if( $diff->days <= 5 ){
            $newWarningDate = $warningAt->addDays(2);
        }

        $account->suspension_warning_at = $newWarningDate;
        $account->save();

        foreach($account->admin_users as $user){
            try{
                Mail::to($user)->send(new AccountSuspensionWarningEmail($user, $account));
            }catch(Exception $e){
                Log::error($e->getTraceAsString());
            }
        }

    }
}
