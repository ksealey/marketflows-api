<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\PaymentMethod;
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
     * Update account
     * 
     */
    public function update(Request $request)
    {
        $rules = [
            'name' => 'bail|min:1|max:64',
        ];

        $validator = validator($request->all(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $account = $request->user()->account;

        if( $request->filled('name') )
            $account->name = $request->name;

        $account->save();

        return response($account);
    }

    /**
     * Close Account
     * 
     */
    public function delete(Request $request)
    {
        $validator = validator($request->rules(),[
            'confirm_close' => 'required|boolean'
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        if( ! $request->confirm_close ){
            return response([
                'error' => 'You must confirm that you would like to close the account. Do this by setting confirm_close to 1.'
            ], 400);
        }

        //  Make sure the account does not have a balance
        $billing = $account->billing;
        if( $billing->past_due_amount ){
            return response([
                'error' => 'You cannot close an account with a past due amount.'
            ], 400);
        }

        //  Delete Phone Numbers
        
        //  Delete Users

        //  Delete Billing

        //  Delete Account

        //  
        //  Delete more stuff...
        //

        //
        //  Create closing statement
        //

        //
        //  Pay closing statement
        //

         
    }
}
