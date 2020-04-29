<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Billing;
use App\Models\User;
use App\Models\Auth\EmailVerification;
use App\Mail\Auth\EmailVerification as UserEmailVerificationMail;
use \App\Rules\CountryRule;
use Validator;
use DB;
use Exception;
use Mail;
use Log;

class RegisterController extends Controller
{
    /**
     * Handle an incoming account registration
     * 
     * @param Illuminate\Http\Request
     * 
     * @return Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $rules = [
            'account_name'          => 'bail|required|min:4|max:64',
            'account_type'          => 'bail|required|in:BASIC,ANALYTICS,ANALYTICS_PRO',
            'timezone'              => 'bail|required|timezone',
            'first_name'            => 'bail|required|min:2|max:32',
            'last_name'             => 'bail|required|min:2|max:32',
            'email'                 => 'bail|required|email|max:128|unique:users,email',
            'password' => [
                'bail',
                'required',
                'min:8',
                'regex:/(?=.*[0-9])(?=.*[A-Z])/'
            ]
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first(),
            ], 400);
        }

        DB::beginTransaction();

        try{
            //  Create account
            $account = Account::create([
                'name'                => $request->account_name,
                'account_type'        => $request->account_type,
                
                'default_tts_voice'   => 'Joanna',
                'default_tts_language'=> 'en-US',
            ]);

            //  Setup billing for account
            Billing::create([
                'account_id'          => $account->id,
                'period_starts_at'    => now()->format('Y-m-d'),
                'period_ends_at'      => now()->addDays(7)->format('Y-m-d')
            ]);

            //  Create this user
            $user = User::create([
                'account_id'                => $account->id,
                'role'                      => User::ROLE_ADMIN,
                'timezone'                  => $request->timezone,
                'first_name'                => $request->first_name,
                'last_name'                 => $request->last_name,
                'email'                     => $request->email,
                'password_hash'             => bcrypt($request->password),
                'auth_token'                => str_random(255),
                'settings'                  => [
                    'email_alerts_enabled' => true,
                    'sms_alerts_enabled'   => false,
                ]
            ]);

            //  Add verification mail to queue
            Mail::to($user->email)
                ->later(
                    now(), 
                    new UserEmailVerificationMail($user)
                );
        }catch(Exception $e){
            DB::rollBack();
           
            throw $e;
        }

        DB::commit(); 

        $account->payment_methods = [];
        $account->past_due_amount = number_format(0.00, 2);
        
        return response([
            'auth_token'    => $user->auth_token,
            'user'          => $user,
            'account'       => $account,
            'first_login'   => true
        ], 201);
    }
}
