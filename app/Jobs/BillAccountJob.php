<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Mail\AccountSuspended as AccountSuspendedEmail;
use App\Mail\BillingStatementReceipt as BillingStatementReceiptEmail;
use App\Mail\PaymentMethodFailed as PaymentMethodFailedEmail;
use App\Models\Account;
use App\Models\User;
use App\Models\Billing;
use App\Models\Statement;
use App\Models\StatementItem;
use App\Models\PaymentMethod;
use Mail;
use Exception;
use Log;
use DateTime;

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
        $billing = $this->billing;
        $account = $billing->account;
        $users   = $account->admin_users;

        //
        //  If the account does not have a valid payment method when it's time to pay, suspend the account
        //
        $paymentMethods = $account->payment_methods;
        if( ! count($paymentMethods) ){
            //
            //  Email the user and let them know that they must add a payment method
            //

            //  Suspend account
            $releaseNumbersAt               = now()->addDays(7);
            $account->suspended_at          = now();
            $account->suspension_code       = Account::SUSPENSION_CODE_NO_PAYMENT_METHOD;
            $account->suspension_message    = 'Your account has been suspended. To re-enable add a valid payment method. If a valid payment method is not added before ' . $releaseNumbersAt->format('M, j Y') . ', all numbers tied to this account will be released.';
            $account->suspension_warning_at = now()->addDays(2);
            $account->save();

            foreach( $users as $user ){
                try{
                    Mail::to($user->email)->send(new AccountSuspendedEmail($user, $account));
                }catch(Exception $e){
                    Log::error($e->getTraceAsString());
                }
            }

            return; // Leave billing locked
        } 

        //  
        //  Try billing account, starting with the primary payment method
        //
        foreach( $billing->unpaid_statements as $statement ){
            $total       = $statement->total;
            $periodStart = new DateTime($statement->period_starts_at);
            $periodEnd   = new DateTime($statement->period_ends_at);
            $description = env('APP_NAME') . ' Statement ' . $periodStart->format('M, j Y') . ' - ' . $periodEnd->format('M, j Y');
            
            $chargeId = null;
            foreach( $paymentMethods as $paymentMethod ){
                $chargeId = $paymentMethod->charge($total, $description);
                if( $chargeId )
                    break;
            }

            if( $chargeId ){
                $statement->paid_at   = now();
                $statement->charge_id = $chargeId;
                $statement->payment_method_id = $paymentMethod->id;
                $statement->save();
                foreach( $users as $user ){
                    try{
                        Mail::to($user->email)->send(new BillingStatementReceiptEmail($user, $statement) );
                    }catch(Exception $e){
                        Log::error($e->getTraceAsString());
                    }
                }
            }else{
                //
                //  No payment method worked
                //
                $billing->attempts++;
                if( $billing->attempts >= 3 ){
                    //  Suspend account and leave billing locked
                    $billing->save();

                    $releaseNumbersAt               = now()->addDays(7);
                    $account->suspended_at          = now();
                    $account->suspension_code       = Account::SUSPENSION_CODE_TOO_MANY_FAILED_BILLING_ATTEMPTS;
                    $account->suspension_message    = 'Your account has been suspended for too many failed billing attempts. To re-enable add a valid payment method. If a valid payment method is not added by ' . $releaseNumbersAt->format('M, j Y') . ', all numbers tied to this account will be released.';
                    $account->suspension_warning_at = now()->addDays(2);
                    $account->save();

                    foreach( $users as $user ){
                        try{
                            Mail::to($user->email)->send( new AccountSuspendedEmail($user, $account) );
                        }catch(Exception $e){
                            Log::error($e->getTraceAsString());
                        }
                    }
                }else{
                    //  Set to re-bill in 2 days and unlock billing
                    $billing->bill_at   = now()->addDays(2);
                    $billing->locked_at = null;
                    $billing->save();

                    //  Let users know that payment method failed
                    foreach( $users as $user ){
                        try{
                            Mail::to($user)->send(new PaymentMethodFailedEmail($user, $paymentMethods[0], $statement) );
                        }catch(Exception $e){
                            Log::error($e->getTraceAsString());
                        }
                    }
                }

                return;
            }
        }

        $account->suspended_at          = null;
        $account->suspension_code       = null;
        $account->suspension_warning_at = null;
        $account->suspension_message    = null;
        $account->save();

        $billing->last_billed_at = now();
        $billing->attempts       = 0;
        $billing->bill_at        = null;
        $billing->locked_at      = null;
        $billing->save();
    }
}
