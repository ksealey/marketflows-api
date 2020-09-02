<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\BillingStatement;
use App\Models\Company;
use App\Models\User;
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
        $config    = config('services.twilio.languages');
        $languages = array_keys($config);
        $voiceKey  = $request->tts_language && in_array($request->tts_language, $languages) ? $request->tts_language : 'en-US';
        $voices    = array_keys($config[$voiceKey]['voices']); 

        $rules = [
            'name'          => 'bail|min:1|max:64',
            'tts_language'  => 'bail|in:' . implode(',', $languages),
            'tts_voice'     => ['bail', 'required_with:tts_language', 'in:' . implode(',', $voices)]
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

        if( $request->filled('tts_language') )
            $account->tts_language = $request->tts_language;

        if( $request->filled('tts_voice') )
            $account->tts_voice = $request->tts_voice;
        
        $account->save();

        return response($account);
    }

    /**
     * Close Account
     * 
     */
    public function delete(Request $request)
    {
        if( ! $request->confirm_close ){
            return response([
                'error' => 'You must confirm that you would like to close the account. Do this by setting confirm_close to 1.'
            ], 400);
        }

        //
        //  Make sure there are no unpaid statements
        //
        $user       = $request->user();
        $account    = $user->account;
        $statements = BillingStatement::where('billing_id',  $account->billing->id)
                                        ->whereNull('paid_at')
                                        ->get();
        if( count($statements) ){
            return response([
                'error' => 'You must first pay all unpaid statements to close your account'
            ], 400);
        }

        //  Remove companies and resources
        $companies = Company::where('account_id', $account->id)->get();
        foreach($companies as $company){
            $user->deleteCompany($company);
        }

        //  Remove all users
        User::where('account_id', $account->id)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => now(),
                'deleted_by' => $user->id,
                'email'      => DB::raw("CONCAT('__DELETED__', email, '__DELETED__')")
            ]);

        //  Remove account and billing
        $account->billing->delete();

        $account->deleted_at = now();
        $account->deleted_by = $user->id;
        $account->save();

        return response([
            'message' => 'Bye'
        ]);
    }
}
