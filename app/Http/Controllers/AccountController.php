<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use App\Models\CreditCode;
use Illuminate\Validation\Rule;
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
            'name'                  => 'bail|min:1|max:64',
            'plan'                  => 'bail|in:BASIC,AGENCY,ENTERPRISE',
            'auto_reload_enabled'   => 'bail|boolean',
            'auto_reload_minimum'   => [
                'bail', 
                Rule::requiredIf($request->filled('auto_reload_amount')), 
                'numeric',
                'min:10', 
                'max:9999',
                'lte:auto_reload_amount'
            ],
            'auto_reload_amount'    => [
                'bail', 
                Rule::requiredIf($request->filled('auto_reload_minimum')), 
                'numeric',
                'min:10', 
                'max:9999'
            ]
        ];

        $validator = Validator::make($request->all(), $rules);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $account = $request->user()->account;

        if( $request->filled('auto_reload_minimum') )
            $account->auto_reload_minimum = $request->auto_reload_minimum;
        
        if( $request->filled('auto_reload_amount') )
            $account->auto_reload_amount = $request->auto_reload_amount;

        if( $request->filled('auto_reload_enabled') ){
            //  Enable or disable auto-reload
            $account->auto_reload_enabled_at = $request->auto_reload_enabled ? ($account->auto_reload_enabled_at ?: now() ) : null;
            
            if( $account->auto_reload_enabled_at && ! $account->primary_payment_method )
                return response([
                    'error' => 'You must first add a primary payment method before before enabling auto reload.'
                ], 400);

            //  Charge account immediately if the balance is too low
            if( $account->shouldAutoReload() )
                $account->autoReload();
        }

        if( $request->filled('name') )
            $account->name = $request->name;

        if( $request->filled('plan') )
            $account->plan = $request->plan;

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

    public function applyCreditCode(Request $request)
    {
        $rules = [
            'code' => [
                'required',
                'regex:/^\bMKT-\b[0-9A-z]{16}$/'
            ]
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user       = $request->user();
        $creditCode = CreditCode::where('code', $request->code)->first();
        if( ! $creditCode || ($creditCode->account_id && $creditCode->account_id != $user->account_id ) )
            return response([
                'error' => 'Invalid credit code'
            ], 400);

        DB::beginTransaction();
       
        try{
            $account = $user->account;
            $account->balance = $account->balance + $creditCode->amount;
            $account->save();

            $creditCode->delete();

            DB::commit();

            return response($account);
        }catch(Exception $e){
            DB::rollBack();

            throw $e;
        }
    }
}
