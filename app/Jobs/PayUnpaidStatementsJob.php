<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\PaymentMethod;
use App\Models\Alert;
use App\Models\Account;
use App\Models\Billing;
use App\Models\BillingStatement;
use App\Models\User;
use App\Mail\BillingReceipt;
use App\Mail\PaymentMethodFailed;
use Mail;
use DB;
use App;

class PayUnpaidStatementsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $paymentMethod;

    protected $paymentManager;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(PaymentMethod $paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->paymentManager = App::make('App\Helpers\PaymentManager');
       
        $paymentMethod = $this->paymentMethod;
        $account       = $paymentMethod->account;
        $billing       = $account->billing;

        if( $billing->locked_at ) return;
        
        $statements = BillingStatement::whereNull('paid_at')
                                      ->where('billing_id', $billing->id)
                                      ->get();

        if( ! count($statements) )
            return;

        //  Lock billing until we're done
        $billing->locked_at = now();
        $billing->save();
        
        foreach( $statements as $statement ){
            $payment = $this->paymentManager->charge($paymentMethod, $statement->total);
            if( ! $payment ){
                foreach( $account->admin_users as $user ){
                    Alert::create([
                        'user_id'       => $user->id,  
                        'category'      => Alert::CATEGORY_PAYMENT,
                        'type'          => Alert::TYPE_DANGER,
                        'title'         => 'Payment method failed',
                        'message'       => 'Payment method ' . $paymentMethod->brand . ' ending in ' . $paymentMethod->last_4 . ' has failed. Please update your payment method to avoid any disruptions in service.',
                    ]);

                    Mail::to($user)
                        ->send(new PaymentMethodFailed($user, $paymentMethod, $statement));
                }
               
                return;
            }

            $statement->payment_id = $payment->id;
            $statement->paid_at    = now();
            $statement->save();
            
            foreach( $account->admin_users as $user ){
                Mail::to($user)
                    ->queue(new BillingReceipt($user, $statement, $paymentMethod, $payment));
            }

            sleep(2);
        }

        $billing->locked_at = null;
        $billing->save();
        
        if( $account->suspension_code == Account::SUSPENSION_CODE_OUSTANDING_BALANCE )
            $account->unsuspend();
    }
}
