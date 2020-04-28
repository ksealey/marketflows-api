<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Mail\AccountSuspended as AccountSuspendedEmail;
use App\Models\Account;
use App\Models\User;
use App\Models\Billing;
use App\Models\Statement;
use App\Models\StatementItem;
use App\Models\PaymentMethod;
use Mail;

class BillAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $billing;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Billing $billing)
    {
        $this->billing = $billing;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //  Make sure the account has a payment method
        $account = $this->billing->account;

        $paymentMethods = $account->paymentMethods;
        if( ! count($paymentMethods) ){
            //
            //  Email the user and let them know that they must add a payment method
            //
            //  Suspend activity on account
            $account->suspended_at       = now();
            $account->suspension_code    = Account::SUSPENSION_CODE_PAYMENT_METHOD;
            $account->suspension_message = 'Your account has been suspended - To re-enable add a valid payment method.';
            $account->save();
             
            $users = User::where('account_id', $account->id)
                        ->where('role', User::ROLE_ADMIN)
                        ->get();

            foreach( $users as $user ){
                Mail::to($user->email)->send(
                    new AccountSuspendedEmail($user, $account)
                );
            }

            return;
        }

    }
}
