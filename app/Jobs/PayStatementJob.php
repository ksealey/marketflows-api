<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Alert;
use App\Models\Billing;
use App\Models\BillingStatement;
use App\Models\BillingStatementItem;
use App\Models\User;
use App\Mail\BillingReceipt;
use App\Mail\PaymentMethodFailed;
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
        $this->statement = $statement;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $paymentManager = App::make('App\Helpers\PaymentManager');
        $statement      = $this->statement;
        $billing        = $statement->billing;
        $account        = $billing->account;
        $paymentMethod  = $account->primary_payment_method; 
        
        $payment = $paymentManager->charge($paymentMethod, $statement->total);
        $user    = User::find($paymentMethod->created_by);

        if( $payment ){
            $statement->payment_id = $payment->id;
            $statement->paid_at    = now();
            $statement->save();
            
            Mail::to($user)
                ->queue(new BillingReceipt($user, $statement, $paymentMethod, $payment));
        }else{
            Alert::create([
                'account_id'    => $user->account_id,
                'user_id'       => $user->id,  
                'category'      => Alert::CATEGORY_PAYMENT,
                'type'          => Alert::TYPE_DANGER,
                'title'         => 'Payment method failed',
                'message'       => 'Payment method ' . $paymentMethod->brand . ' ending in ' . $paymentMethod->last_4 . ' has failed. Please update your payment method to avoid any disruptions in service.',
            ]);

            Mail::to($user)
                ->queue(new PaymentMethodFailed($user, $paymentMethod, $statement));
        }
    }
}
