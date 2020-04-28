<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function read(Request $request)
    {
        $account = $request->user()->account;
        $billing = $account->billing; 

        return response([
            'account_type'          => $account->pretty_account_type,
            'monthly_fee'           => number_format($account->monthly_fee, 2),
            'primary_payment_method'=> $account->primary_payment_method,
            'past_due_amount'       => number_format($billing->past_due_amount, 2),
            'current_billing_period'=> $billing->current_billing_period,
            'bill_at'               => $billing->bill_at
        ]);
    }
}
