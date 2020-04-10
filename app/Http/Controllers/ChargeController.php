<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use App\Models\Charge;
use Validator;
use DB;

class ChargeController extends Controller
{
    public function list(Request $request)
    {
        //  Set additional rules
        $rules = [
            'order_by'          => 'in:charges.description,charges.created_at,charges.amount,payment_methods.last_4',
            'company_id'        => 'numeric',
            'payment_method_id' => 'numeric'
        ];

        $searchFields = [
            'charges.amount',
            'charges.description',
            'payment_methods.last_4',
        ];

        $user    = $request->user();
        $account = $user->account;

        //  Build Query
        $query = DB::table('charges')
                    ->select(['charges.*', 'payment_methods.last_4 AS payment_method_last_4', 'payment_methods.deleted_at AS payment_method_deleted_at'])
                    ->leftJoin('payment_methods', 'payment_methods.id', 'charges.payment_method_id')
                    ->whereIn('charges.payment_method_id', function($query) use($account){
                        $query->select('id')
                            ->from('payment_methods')
                            ->where('account_id', $account->id);
                    });

        if( $request->payment_method_id )
            $query->where('charges.payment_method_id', $request->payment_method_id);

        return parent::results(
            $request,
            $query,
            $rules,
            $searchFields,
            'charges.created_at'
        );
    }

    public function read(Request $request, Charge $charge)
    {
        return response($charge);
    }
}
