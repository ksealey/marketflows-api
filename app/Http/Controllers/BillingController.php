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

        return response($billing);
    }


    public function current(Request $request)
    {
        $account = $request->user()->account;
        $billing = $account->billing;
        $total   = 0;

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

        $total = $serviceTotal + $localNumberTotal + $tollFreeNumberTotal + $localMinutesTotal + $tollFreeMinutesTotal + $transMinutesTotal + $storageTotal;
        $items = [
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_SERVICE),
                'quantity'             => $serviceQuantity,
                'price'                => $billing->price(Billing::ITEM_SERVICE),
                'total'                => $serviceTotal
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_NUMBERS_LOCAL),
                'quantity'             => $localNumberQuantity,
                'price'                => $billing->price(Billing::ITEM_NUMBERS_LOCAL),
                'total'                => $localNumberTotal
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_NUMBERS_TOLL_FREE),
                'quantity'             => $tollFreeNumberQuantity,
                'price'                => $billing->price(Billing::ITEM_NUMBERS_TOLL_FREE),
                'total'                => $tollFreeNumberTotal,
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_MINUTES_LOCAL),
                'quantity'             => $localMinutesQuantity,
                'price'                => $billing->price(Billing::ITEM_MINUTES_LOCAL),
                'total'                => $localMinutesTotal,
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_MINUTES_TOLL_FREE),
                'quantity'             => $tollFreeMinutesQuantity,
                'price'                => $billing->price(Billing::ITEM_MINUTES_TOLL_FREE),
                'total'                => $tollFreeMinutesTotal,
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_MINUTES_TRANSCRIPTION),
                'quantity'             => $transMinutesQuantity,
                'price'                => $billing->price(Billing::ITEM_MINUTES_TRANSCRIPTION),
                'total'                => $transMinutesTotal
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_STORAGE_GB),
                'quantity'             => $storageQuantity,
                'price'                => $billing->price(Billing::ITEM_STORAGE_GB),
                'total'                => $storageTotal
            ],
        ];

        foreach( $billing->account->services as $service ){
            $_serviceTotal = $service->total();

            $items[] = [
                'type'                 => 'SERVICE',
                'label'                => $service->label(),
                'quantity'             => $service->quantity(),
                'price'                => $service->price(),
                'total'                => $_serviceTotal
            ];

            $total += $_serviceTotal;
        }

        return response([
            'items' => $items,
            'total' => $total
        ]);
    }
}
