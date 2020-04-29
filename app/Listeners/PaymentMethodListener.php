<?php

namespace App\Listeners;

use App\Events\PaymentMethodEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Mail\AccountUnsuspended as AccountUnsuspendedEmail;
use App\Mail\BillingStatementReceipt as BillingStatementReceiptEmail;
use App\Mail\PaymentMethodFailed as PaymentMethodFailedEmail;
use Mail;
use DateTime;
use Log;
use Exception;

class PaymentMethodListener
{

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  PaymentMethodEvent  $event
     * @return void
     */
    public function handle(PaymentMethodEvent $event)
    {
        if( $event->action === 'create' ){
            $user     = $event->user;
            $account  = $user->account;

            //  The account is not suspended, so there is noting to do
            if( ! $account->suspended_at ) return;

            //  Pay any past due statements and unsuspend account if applicable
            $paymentMethod = $event->paymentMethods[0];
            $billing       = $account->billing;
            
            foreach( $billing->unpaid_statements as $statement ){
                $total       = $statement->total;
                $periodStart = new DateTime($statement->period_starts_at);
                $periodEnd   = new DateTime($statement->period_ends_at);
                $description = env('APP_NAME') . ' Statement ' . $periodStart->format('M, j Y') . ' - ' . $periodEnd->format('M, j Y');
                $chargeId    = $paymentMethod->charge($total, $description);
                
                if( $chargeId ){
                    $statement->paid_at   = now();
                    $statement->charge_id = $chargeId;
                    $statement->payment_method_id = $paymentMethod->id;
                    $statement->save();
                    try{
                        Mail::to($user->email)->send(new BillingStatementReceiptEmail($user, $statement) );
                    }catch(Exception $e){
                        Log::error($e->getTraceAsString());
                    }
                }else{
                    $billing->attempts = $billing->attempts + 1;
                    Mail::to($user)->send(new PaymentMethodFailedEmail($user, $paymentMethod, $statement));
                    return;
                }
            }
           
            $account->suspended_at          = null;
            $account->suspension_code       = null;
            $account->suspension_warning_at = null;
            $account->suspension_message  = null;
            $account->save();

            $billing->attempts       = 0;
            $billing->last_billed_at = now();
            $billing->locked_at      = null; // Unlock billing for next billing period
            $billing->save();

            Mail::to($user)->send(new AccountUnsuspendedEmail($user));
        }
        
    }
}
