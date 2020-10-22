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
    public $paymentMethod = null;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(BillingStatement $statement, $paymentMethod = null)
    {
        $this->statement     = $statement;
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $statement            = $this->statement;
        $statement->locked_at = now();
        $statement->save();

        $paymentManager = App::make('App\Helpers\PaymentManager');
        
        $billing        = $statement->billing;
        $account        = $billing->account;
        
        if( $this->paymentMethod ){
            $paymentMethod = $this->paymentMethod;
        }else{
            $paymentMethod  = $account->primary_payment_method; 
        }

        $user    = User::find($paymentMethod->created_by);
        $results = $paymentManager->charge($paymentMethod, $statement);
        $payment = $results->payment;
        
        if( $payment ){
            $statement->next_payment_attempt_at = null;
            $statement->payment_id              = $payment->id;
            $statement->paid_at                 = now();

            Mail::to($user)
                ->queue(new BillingReceipt($user, $statement, $paymentMethod, $payment));

            //  Unsuspend accounts that were suspsended for past due payments if paid in full
            if( $account->suspension_code == Account::SUSPENSION_CODE_OUSTANDING_BALANCE && ! $billing->past_due )
                $account->unsuspend();
        }else{
            $statement->next_payment_attempt_at = now()->addDays(3);

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
        
        $statement->payment_attempts++;
        $statement->locked_at = null;
        $statement->save();
    }
}
