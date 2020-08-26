<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\PaymentMethod;
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
        $statements    = BillingStatement::whereNull('paid_at')
                                         ->where('billing_id', DB::raw(
                                             '(SELECT id 
                                                FROM billing 
                                                WHERE account_id = ' . $paymentMethod->account_id
                                            . ')'
                                         ))
                                         ->get();

        if( ! count($statements) )
            return;

        $user = User::find($paymentMethod->created_by);
        foreach( $statements as $statement ){
            $payment = $this->paymentManager->charge($paymentMethod, $statement->total());
            if( ! $payment ){
                if( $user ){
                    Mail::to($user)
                        ->send(new PaymentMethodFailed($user, $paymentMethod, $statement));
                }
                return;
            }

            $statement->payment_id = $payment->id;
            $statement->paid_at    = now();
            $statement->save();
            
            if( $user ){
                Mail::to($user)
                    ->send(new BillingReceipt($user, $payment));
            }

            sleep(2);
        }

        $account = $paymentMethod->account;
        if( $account->suspension_code == Account::SUSPENSION_CODE_OUSTANDING_BALANCE )
            $account->unsuspend();
    }
}
