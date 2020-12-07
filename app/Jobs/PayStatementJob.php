<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Account;
use App\Models\Alert;
use App\Models\Billing;
use App\Models\BillingStatement;
use App\Models\User;
use App\Mail\BillingReceipt;
use App\Mail\PaymentMethodFailed;
use App\Mail\NoPaymentMethodFound;
use Carbon\Carbon;
use Mail;
use App;

class PayStatementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $statement;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(BillingStatement $statement)
    {
        $this->statement     = $statement;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $statement      = $this->statement;
        $billing        = $statement->billing;
        $account        = $billing->account;
        $adminUsers     = $account->admin_users;
        $paymentMethods = $account->payment_methods;

        if( ! count($paymentMethods) ){
            // 
            // Let the user know they need to add a payment method 
            // 
            $statement->next_payment_attempt_at = now()->addDays(3);
            $statement->save();
            
            foreach( $adminUsers as $user ){
                Alert::create([
                    'account_id'    => $user->account_id,
                    'user_id'       => $user->id,  
                    'category'      => Alert::CATEGORY_PAYMENT,
                    'type'          => Alert::TYPE_DANGER,
                    'title'         => 'No payment method found',
                    'message'       => 'No payment method was found on your account. Please add a payment method to avoid any disruptions in service.',
                ]);

                Mail::to($user)->send(new NoPaymentMethodFound($user));
            }

            return;
        }

        $paymentManager = App::make('App\Helpers\PaymentManager');
        foreach( $paymentMethods  as $paymentMethod ){
            $results = $paymentManager->charge($paymentMethod, $statement);
            $payment = $results->payment;
            if( $payment ){
                if( ! $billing->past_due ){
                    $account->removePaymentAlerts();

                    if( $account->suspension_code == Account::SUSPENSION_CODE_OUSTANDING_BALANCE ){
                        $account->unsuspend();
                    } 
                }

                foreach( $adminUsers as $user ){
                    Mail::to($user)
                        ->queue(new BillingReceipt($user, $statement, $paymentMethod, $payment));
                }

                return;
            }else{
                if( $paymentMethod->primary_method ){
                    foreach( $adminUsers as $user ){
                        Alert::create([
                            'account_id'    => $user->account_id,
                            'user_id'       => $user->id,  
                            'category'      => Alert::CATEGORY_PAYMENT,
                            'type'          => Alert::TYPE_DANGER,
                            'title'         => 'Payment failed',
                            'message'       => 'Payment method ' . $paymentMethod->brand . ' ending in ' . $paymentMethod->last_4 . ' has failed. Please update your payment method to avoid any disruptions in service.',
                        ]);

                        Mail::to($user)
                            ->queue(new PaymentMethodFailed($user, $paymentMethod, $statement));
                    }
                }
            }
        }
    }
}
