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
        $billing->monthly_fee            = $billing->price(Billing::ITEM_SERVICE);

        return response($billing);
    }


    public function current(Request $request)
    {
        return response(
            $request->user()
                    ->account
                    ->billing
                    ->current()
        );
    }
}
