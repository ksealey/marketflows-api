<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Rules\DateFilterRule;
use App\Models\PaymentMethod;
use App\Models\Charge;
use Validator;

class ChargeController extends Controller
{
    public function list(Request $request)
    {
        //  Set additional rules
        $rules = [
            'order_by'          => 'in:description,created_at,amount,payment_method_id',
            'company_id'        => 'numeric',
            'payment_method_id' => 'numeric'
        ];

        $user    = $request->user();
        $account = $user->account;

        //  Build Query
        $query = Charge::whereIn('payment_method_id', function($query) use($account){
            $query->select('id')
                  ->from('payment_methods')
                  ->where('account_id', $account->id);
        });

        if( $request->payment_method_id )
            $query->where('payment_method_id', $request->payment_method_id);
        
        if( $request->search )
            $query->where('description', 'like', '%' . $request->search . '%');

        //  Pass along to parent for listing
        return $this->listRecords(
            $request,
            $query,
            $rules,
            function($records){
                $paymentMethodIds = [];
                foreach( $records as $r )
                    $paymentMethodIds[] = $r->payment_method_id;
                
                $paymentMethodsById = [];
                $paymentMethods     = PaymentMethod::whereIn('id', $paymentMethodIds)->withTrashed()->get();
                foreach($paymentMethods as $pm)
                    $paymentMethodsById[$pm->id] = $pm;

                foreach( $records as $record )
                    $record->payment_method = $paymentMethodsById[$record->payment_method_id] ?? null;

                return $records;
            }
        );
    }

    public function read(Request $request, Charge $charge)
    {
        return response($charge);
    }
}
