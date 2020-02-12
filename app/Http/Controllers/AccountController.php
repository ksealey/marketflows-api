<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use Validator;
use Exception;

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
            'name'                  => 'bail|min:1|max:64',
            'timezone'              => 'bail|timezone',
            'plan'                  => 'bail|in:BASIC,AGENCY,ENTERPRISE',
            'timezone'              => 'bail|timezone',
            'auto_reload_enabled'   => 'bail|boolean',
            'auto_reload_minimum'   => 'bail|numeric|min:10|max:9999',
            'auto_reload_amount'    => 'bail|numeric|min:10|max:9999',
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $account = $request->user()->account;

        if( $request->filled('name') )
            $account->name = $request->name;

        if( $request->filled('timezone') )
            $account->timezone = $request->timezone;

        if( $request->filled('plan') )
            $account->plan = $request->plan;

        if( $request->filled('auto_reload_enabled') )
            $account->auto_reload_enabled_at = $request->auto_reload_enabled ? ($account->auto_reload_enabled_at ?: now() ) : null;
        
        if( $request->filled('auto_reload_minimum') )
            $account->auto_reload_minimum = $request->auto_reload_minimum;
        
        if( $request->filled('auto_reload_amount') )
            $account->auto_reload_amount = $request->auto_reload_amount;

        $account->save();

        return response($account);
    }

    /**
     * Fund account
     * 
     */
    public function fund(Request $request)
    {
        $rules = [
            'amount'            => 'bail|required|numeric|min:10|max:9999',
            'payment_method_id' => ['bail', 'required'],
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $account = $request->user()->account;

        $paymentMethod = PaymentMethod::find($request->payment_method_id);
        if( ! $paymentMethod || $paymentMethod->account_id !== $account->id ){
            return response([
                'error' => 'Payment method invalid'
            ], 400);
        }

        if( ! $paymentMethod->isValid() ){
            return response([
                'error' => 'Please try a different payment method'
            ], 400);
        }

        //  Charge the payment method provided
        $amount = floatval($request->amount);
        $charge = $paymentMethod->charge($amount, env('APP_NAME') . ' - Fund Account');
        if( ! $charge )
            return response([
                'error' => 'Payment declined - please try another payment method'
            ], 400);
        
        $account->balance += $amount;
        $account->save();

        return response($charge);
    }
}
