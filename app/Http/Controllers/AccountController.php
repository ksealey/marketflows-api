<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use App\Models\CreditCode;
use Illuminate\Validation\Rule;
use DateTime;
use Validator;
use Exception;
use DB;

class AccountController extends Controller
{
    public function read(Request $request)
    {
        return response($request->user()->account);
    }

    /**
     * Update account attached to
     * 
     */
    public function update(Request $request)
    {
        $rules = [
            'name'         => 'bail|min:1|max:64',
            'account_type' => 'bail|in:' . implode(',', Account::types()),
        ];

        $validator = validator($request->all(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $account = $request->user()->account;
        
        //  Only allow account type changes once  month
        if( $request->filled('account_type') && $account->account_type_updated_at ){
            $now              = new DateTime();
            $allowUpdateAfter = new DateTime($account->account_type_updated_at);
            $allowUpdateAfter->modify('+1 month');

            if( $now->format('Y-m-d') <= $allowUpdateAfter->format('Y-m-d') ){
                return response([
                    'error' => 'account type can only be updated once a month'
                ], 404);
            }
        }

        if( $request->filled('name') )
            $account->name = $request->name;

        if( $request->filled('account_type') )
            $account->account_type = $request->account_type;

        $account->save();

        return response($account);
    }
}
