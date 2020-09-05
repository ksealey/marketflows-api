<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \App\Models\Billing;
use \Carbon\Carbon;

class BillingController extends Controller
{

    public function read(Request $request)
    {
        $account = $request->user()->account;
        $billing = $account->billing;

        $pastDue                    = $billing->past_due;
        $pastDueFormatted           = number_format($pastDue, 2);

        $billing->past_due           = $pastDue;
        $billing->past_due_formatted = $pastDueFormatted;

        $billing->primary_payment_method = $account->primary_payment_method;
        $billing->monthly_fee            = Billing::COST_SERVICE;

        return response($billing);
    }


    public function current(Request $request)
    {
        $account = $request->user()->account;
        $billing = $account->billing;
        $total   = 0;

        $billingPeriodStart     = new Carbon($billing->billing_period_starts_at);
        $billingPeriodEnd       = new Carbon($billing->billing_period_ends_at);
 
        $localNumberQuantity    = $billing->quantity(Billing::ITEM_NUMBERS_LOCAL, $billingPeriodStart, $billingPeriodEnd);
        $localNumberPrice       = $billing->price(Billing::ITEM_NUMBERS_LOCAL);
        $localNumberTotal       = $billing->total(Billing::ITEM_NUMBERS_LOCAL, $localNumberQuantity);

        $tollFreeNumberQuantity = $billing->quantity(Billing::ITEM_NUMBERS_TOLL_FREE, $billingPeriodStart, $billingPeriodEnd);
        $tollFreeNumberPrice    = $billing->price(Billing::ITEM_NUMBERS_TOLL_FREE);
        $tollFreeNumberTotal    = $billing->total(Billing::ITEM_NUMBERS_TOLL_FREE, $tollFreeNumberQuantity);

        $localMinutesQuantity   = $billing->quantity(Billing::ITEM_MINUTES_LOCAL, $billingPeriodStart, $billingPeriodEnd);
        $localMinutesPrice      = $billing->price(Billing::ITEM_MINUTES_LOCAL);
        $localMinutesTotal      = $billing->total(Billing::ITEM_MINUTES_LOCAL, $localMinutesQuantity);

        $tollFreeMinutesQuantity= $billing->quantity(Billing::ITEM_MINUTES_TOLL_FREE, $billingPeriodStart, $billingPeriodEnd);
        $tollFreeMinutesPrice   = $billing->price(Billing::ITEM_MINUTES_TOLL_FREE);
        $tollFreeMinutesTotal   = $billing->total(Billing::ITEM_MINUTES_TOLL_FREE, $tollFreeMinutesQuantity);

        $transMinutesQuantity   = $billing->quantity(Billing::ITEM_MINUTES_TRANSCRIPTION, $billingPeriodStart, $billingPeriodEnd);
        $transMinutesPrice      = $billing->price(Billing::ITEM_MINUTES_TRANSCRIPTION);
        $transMinutesTotal      = $billing->total(Billing::ITEM_MINUTES_TRANSCRIPTION, $transMinutesQuantity);

        $storageQuantity        = $billing->quantity(Billing::ITEM_STORAGE_GB, $billingPeriodStart, $billingPeriodEnd);
        $storagePrice           = $billing->price(Billing::ITEM_STORAGE_GB);
        $storageTotal           = $billing->total(Billing::ITEM_STORAGE_GB, $storageQuantity);

        $total = $localNumberTotal + $tollFreeNumberTotal + $localMinutesTotal + $tollFreeMinutesTotal + $transMinutesTotal + $storageTotal;
        $items = [
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_NUMBERS_LOCAL),
                'quantity'             => $localNumberQuantity,
                'price'                => $localNumberPrice,
                'price_formatted'      => number_format($localNumberPrice, 2),
                'total'                => $localNumberTotal,
                'total_formatted'      => number_format($localNumberTotal, 2)
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_NUMBERS_TOLL_FREE),
                'quantity'             => $tollFreeNumberQuantity,
                'price'                => $tollFreeNumberPrice,
                'price_formatted'      => number_format($tollFreeNumberPrice, 2),
                'total'                => $tollFreeNumberTotal,
                'total_formatted'      => number_format($tollFreeNumberTotal, 2)
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_MINUTES_LOCAL),
                'quantity'             => $localMinutesQuantity,
                'price'                => $localMinutesPrice,
                'price_formatted'      => number_format($localMinutesPrice, 2),
                'total'                => $localMinutesTotal,
                'total_formatted'      => number_format($localMinutesTotal, 2)
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_MINUTES_TOLL_FREE),
                'quantity'             => $tollFreeMinutesQuantity,
                'price'                => $tollFreeMinutesPrice,
                'price_formatted'      => number_format($tollFreeMinutesPrice, 2),
                'total'                => $tollFreeMinutesTotal,
                'total_formatted'      => number_format($tollFreeMinutesTotal, 2)
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_MINUTES_TRANSCRIPTION),
                'quantity'             => $transMinutesQuantity,
                'price'                => $transMinutesPrice,
                'price_formatted'      => number_format($transMinutesPrice, 2),
                'total'                => $transMinutesTotal,
                'total_formatted'      => number_format($transMinutesTotal, 2)
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_STORAGE_GB),
                'quantity'             => $storageQuantity,
                'price'                => $storagePrice,
                'price_formatted'      => number_format($storagePrice, 2),
                'total'                => $storageTotal,
                'total_formatted'      => number_format($storageTotal, 2)
            ],
        ];

        foreach( $billing->account->services as $service ){
            $_serviceTotal = $service->total();
            $_servicePrice = $service->price();
            $items[] = [
                'type'                 => 'SERVICE',
                'label'                => $service->label(),
                'quantity'             => $service->quantity(),
                'price'                => $_servicePrice,
                'price_formatted'      => number_format($_servicePrice, 2),
                'total'                => $_serviceTotal,
                'total_formatted'      => number_format($_serviceTotal, 2)
            ];

            $total += $_serviceTotal;
        }

        return response([
            'items' => $items,
            'total' => $total,
            'total_formatted' => number_format($total, 2)
        ]);
    }
}
