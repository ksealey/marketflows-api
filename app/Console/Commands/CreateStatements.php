<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Billing;
use App\Models\BillingStatement;
use App\Models\BillingStatementItem;
use Carbon\Carbon;
use Mail;
use App;

class CreateStatements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create-statements';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create statements';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        //  Find Billable accounts
        $billings = Billing::where('billing_period_ends_at', '<=', now())
                           ->get();

        if( ! count($billings) ) return;
               
        foreach( $billings as $billing ){
            //
            //  Create statement. This includes monthly service fees, usage and any additional services
            //
            $statement = BillingStatement::create([
                'billing_id'               => $billing->id,
                'billing_period_starts_at' => $billing->billing_period_starts_at,
                'billing_period_ends_at'   => $billing->billing_period_ends_at,
                'payment_attempts'         => 0,
                'next_payment_attempt_at'  => now()
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
            $now = now();

            $items = [
                [
                    'billing_statement_id' => $statement->id,
                    'label'                => $billing->label(Billing::ITEM_SERVICE),
                    'quantity'             => $serviceQuantity,
                    'price'                => $billing->price(Billing::ITEM_SERVICE),
                    'total'                => $serviceTotal,
                    'created_at'           => $now,
                    'updated_at'           => $now
                ],
                [
                    'billing_statement_id' => $statement->id,
                    'label'                => $billing->label(Billing::ITEM_NUMBERS_LOCAL),
                    'quantity'             => $localNumberQuantity,
                    'price'                => $billing->price(Billing::ITEM_NUMBERS_LOCAL),
                    'total'                => $localNumberTotal,
                    'created_at'           => $now,
                    'updated_at'           => $now
                ],
                [
                    'billing_statement_id' => $statement->id,
                    'label'                => $billing->label(Billing::ITEM_NUMBERS_TOLL_FREE),
                    'quantity'             => $tollFreeNumberQuantity,
                    'price'                => $billing->price(Billing::ITEM_NUMBERS_TOLL_FREE),
                    'total'                => $tollFreeNumberTotal,
                    'created_at'           => $now,
                    'updated_at'           => $now
                ],
                [
                    'billing_statement_id' => $statement->id,
                    'label'                => $billing->label(Billing::ITEM_MINUTES_LOCAL),
                    'quantity'             => $localMinutesQuantity,
                    'price'                => $billing->price(Billing::ITEM_MINUTES_LOCAL),
                    'total'                => $localMinutesTotal,
                    'created_at'           => $now,
                    'updated_at'           => $now
                ],
                [
                    'billing_statement_id' => $statement->id,
                    'label'                => $billing->label(Billing::ITEM_MINUTES_TOLL_FREE),
                    'quantity'             => $tollFreeMinutesQuantity,
                    'price'                => $billing->price(Billing::ITEM_MINUTES_TOLL_FREE),
                    'total'                => $tollFreeMinutesTotal,
                    'created_at'           => $now,
                    'updated_at'           => $now
                ],
                [
                    'billing_statement_id' => $statement->id,
                    'label'                => $billing->label(Billing::ITEM_MINUTES_TRANSCRIPTION),
                    'quantity'             => $transMinutesQuantity,
                    'price'                => $billing->price(Billing::ITEM_MINUTES_TRANSCRIPTION),
                    'total'                => $transMinutesTotal,
                    'created_at'           => $now,
                    'updated_at'           => $now
                ],
                [
                    'billing_statement_id' => $statement->id,
                    'label'                => $billing->label(Billing::ITEM_STORAGE_GB),
                    'quantity'             => $storageQuantity,
                    'price'                => $billing->price(Billing::ITEM_STORAGE_GB),
                    'total'                => $storageTotal,
                    'created_at'           => $now,
                    'updated_at'           => $now
                ],
            ];

            foreach( $billing->account->companies as $company ){
                foreach( $company->plugins as $companyPlugin ){
                    if( $plugin->price > 0 ){
                        $items[] = [
                            'billing_statement_id' => $statement->id,
                            'label'                => $companyPlugin->label,
                            'quantity'             => 1,
                            'price'                => $companyPlugin->price,
                            'total'                => $companyPlugin->price
                        ];
                    }
                }
            }

            BillingStatementItem::insert($items);

            $billing->billing_period_starts_at = (new Carbon($billing->billing_period_ends_at))->addDays(1)->startOfDay(); // Start of the next day
            $billing->billing_period_ends_at   = (new Carbon($billing->billing_period_starts_at))->addDays(30)->endOfDay(); // End of day, 30 days from now
            $billing->save();
        }
    }
}
