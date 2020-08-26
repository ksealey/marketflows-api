<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Billing;
use App\Models\BillingStatement;
use App\Models\BillingStatementItem;
use App\Models\User;
use App\Mail\BillingReceipt;
use App\Mail\PaymentMethodFailed;
use Carbon\Carbon;
use Mail;
use App;

class BillAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $billing;

    protected $paymentManager;
    
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
        $this->paymentManager = App::make('App\Helpers\PaymentManager');
        
        $billing = $this->billing;
        
        //
        //  Create statement. This includes monthly service fees, usage and any additional services
        //
        $statement = BillingStatement::create([
            'billing_id'               => $billing->id,
            'billing_period_starts_at' => $billing->billing_period_starts_at,
            'billing_period_ends_at'   => $billing->billing_period_ends_at
        ]);

        $billingPeriodStart     = new Carbon($billing->billing_period_starts_at);
        $billingPeriodEnd       = new Carbon($billing->billing_period_ends_at);

        $serviceQuantity        = $billing->quantity(Billing::ITEM_SERVICE, $billingPeriodStart, $billingPeriodEnd);
        $serviceTotal           = $billing->total(Billing::ITEM_SERVICE, $serviceQuantity);

        $localNumberQuantity    = $billing->quantity(Billing::ITEM_NUMBERS_LOCAL, $billingPeriodStart, $billingPeriodEnd);
        $localNumberTotal       = $billing->total(Billing::ITEM_NUMBERS_LOCAL, $localNumberQuantity);

        $tollFreeNumberQuantity = $billing->quantity(Billing::ITEM_NUMBERS_TOLL_FREE, $billingPeriodStart, $billingPeriodEnd);
        $tollFreeNumberTotal    = $billing->total(Billing::ITEM_NUMBERS_TOLL_FREE, $tollFreeNumberQuantity);

        $localMinutesQuantity   = $billing->quantity(Billing::ITEM_MINUTES_LOCAL, $billingPeriodStart, $billingPeriodEnd);
        $localMinutesTotal      = $billing->total(Billing::ITEM_MINUTES_LOCAL, $localMinutesQuantity);

        $tollFreeMinutesQuantity= $billing->quantity(Billing::ITEM_MINUTES_TOLL_FREE, $billingPeriodStart, $billingPeriodEnd);
        $tollFreeMinutesTotal   = $billing->total(Billing::ITEM_MINUTES_TOLL_FREE, $tollFreeMinutesQuantity);

        $transMinutesQuantity   = $billing->quantity(Billing::ITEM_MINUTES_TRANSCRIPTION, $billingPeriodStart, $billingPeriodEnd);
        $transMinutesTotal      = $billing->total(Billing::ITEM_MINUTES_TRANSCRIPTION, $transMinutesQuantity);

        $storageQuantity        = $billing->quantity(Billing::ITEM_STORAGE_GB, $billingPeriodStart, $billingPeriodEnd);
        $storageTotal           = $billing->total(Billing::ITEM_STORAGE_GB, $storageQuantity);
        $items = [
            [
                'billing_statement_id' => $statement->id,
                'label'                => $billing->label(Billing::ITEM_SERVICE),
                'quantity'             => $serviceQuantity,
                'price'                => $billing->price(Billing::ITEM_SERVICE),
                'total'                => $serviceTotal
            ],
            [
                'billing_statement_id' => $statement->id,
                'label'                => $billing->label(Billing::ITEM_NUMBERS_LOCAL),
                'quantity'             => $localNumberQuantity,
                'price'                => $billing->price(Billing::ITEM_NUMBERS_LOCAL),
                'total'                => $localNumberTotal
            ],
            [
                'billing_statement_id' => $statement->id,
                'label'                => $billing->label(Billing::ITEM_NUMBERS_TOLL_FREE),
                'quantity'             => $tollFreeNumberQuantity,
                'price'                => $billing->price(Billing::ITEM_NUMBERS_TOLL_FREE),
                'total'                => $tollFreeNumberTotal,
            ],
            [
                'billing_statement_id' => $statement->id,
                'label'                => $billing->label(Billing::ITEM_MINUTES_LOCAL),
                'quantity'             => $localMinutesQuantity,
                'price'                => $billing->price(Billing::ITEM_MINUTES_LOCAL),
                'total'                => $localMinutesTotal,
            ],
            [
                'billing_statement_id' => $statement->id,
                'label'                => $billing->label(Billing::ITEM_MINUTES_TOLL_FREE),
                'quantity'             => $tollFreeMinutesQuantity,
                'price'                => $billing->price(Billing::ITEM_MINUTES_TOLL_FREE),
                'total'                => $tollFreeMinutesTotal,
            ],
            [
                'billing_statement_id' => $statement->id,
                'label'                => $billing->label(Billing::ITEM_MINUTES_TRANSCRIPTION),
                'quantity'             => $transMinutesQuantity,
                'price'                => $billing->price(Billing::ITEM_MINUTES_TRANSCRIPTION),
                'total'                => $transMinutesTotal
            ],
            [
                'billing_statement_id' => $statement->id,
                'label'                => $billing->label(Billing::ITEM_STORAGE_GB),
                'quantity'             => $storageQuantity,
                'price'                => $billing->price(Billing::ITEM_STORAGE_GB),
                'total'                => $storageTotal
            ],
        ];

        foreach( $billing->account->services as $service ){
            $items[] = [
                'billing_statement_id' => $statement->id,
                'label'                => $service->label(),
                'quantity'             => $service->quantity(),
                'price'                => $service->price(),
                'total'                => $service->total()
            ];
        }

        BillingStatementItem::insert($items);

        //
        //  Charge payment method
        // 
        $account       = $billing->account;
        $paymentMethod = $account->primary_payment_method; 
        
        $payment = $this->paymentManager->charge($paymentMethod, $statement->total());
        $user    = User::find($paymentMethod->created_by);

        //
        //  Unlock billing and forward billing period
        //
        $billing->locked_at = null;
        $billing->billing_period_starts_at = (new Carbon($billing->billing_period_ends_at))->addDays(1)->startOfDay(); // Start of the next day
        $billing->billing_period_ends_at   = (new Carbon($billing->billing_period_starts_at))->addDays(30)->endOfDay(); // End of day, 30 days from now
        $billing->save();

        if( $payment ){
            $statement->payment_id = $payment->id;
            $statement->paid_at    = now();
            $statement->save();
            
            Mail::to($user)
                ->queue(new BillingReceipt($user, $statement, $paymentMethod, $payment));
        }else{
            Mail::to($user)
                ->queue(new PaymentMethodFailed($user, $paymentMethod, $statement));
        }
    }
}
