<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Billing;
use App\Models\User;
use App\Models\PaymentMethod;
use App\Models\UserSettings;
use App\Models\Auth\EmailVerification;
use App\Mail\Auth\EmailVerification as UserEmailVerificationMail;
use \App\Rules\CountryRule;
use \Carbon\Carbon;
use \Stripe\Stripe;
use \Stripe\Customer;
use \Stripe\Card;
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
            'first_name'            => 'bail|required|min:2|max:32',
            'last_name'             => 'bail|required|min:2|max:32',
            'email'                 => 'bail|required|email|max:128|unique:users,email',
            'password' => [
                'bail',
                'required',
                'min:8',
                'regex:/(?=.*[0-9])(?=.*[A-Z])/'
            ],
            'timezone'              => 'bail|required|timezone',
            'payment_token'         => 'required' 
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first(),
            ], 400);
        }

        //
        //  Make sure it's not a spoof email
        //
        $spoofDomains = config('app.spoof_email_domains');
        $emailDomain  = explode('@', $request->email);
        $emailDomain  = end($emailDomain);
        if( in_array($emailDomain, $spoofDomains) ){
            return response([
                'error' => 'Invalid email domain'
            ], 400);
        }

        //
        //  Try to add customer with payment method
        //
        $customer = null;
        $card = null;

        DB::beginTransaction();

        try{
            Stripe::setApiKey(env('STRIPE_SK'));
            
            $customer = Customer::create([
                'description' => $request->account_name,
                'source'      => $request->payment_token
            ]);
            
            $card = $customer->sources->data[0];

            //  Create account
            $account = Account::create([
                'name'                => $request->account_name,
                'default_tts_voice'   => 'Joanna',
                'default_tts_language'=> 'en-US',
            ]);

            //  Setup billing for account
            Billing::create([
                'account_id'                => $account->id,
                'billing_period_starts_at'  => now()->startOfDay(),// Start of the next day
                'billing_period_ends_at'    => now()->addDays(30)->endOfDay(), // End of day, 30 days from now,
                'external_id'               => $customer->id
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
                'last_login_at'             => now()
            ]);
            UserSettings::create([
                'user_id' => $user->id
            ]);

            //  Add initial payment method
            $expiration = new Carbon($card->exp_year . '-' . $card->exp_month . '-01 00:00:00'); 
            $expiration->endOfMonth();
            PaymentMethod::create([
                'account_id'     => $user->account_id,
                'created_by'     => $user->id,
                'external_id'    => $card->id,
                'last_4'         => $card->last4,
                'type'           => $card->funding,
                'brand'          => ucfirst($card->brand),
                'expiration'     => $expiration->format('Y-m-d'),
                'primary_method' => true
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
        $account->in_demo         = true;
        
        return response([
            'auth_token'    => $user->auth_token,
            'user'          => $user,
            'account'       => $account,
            'first_login'   => true
        ], 201);
    }

    /**
     * Verify email address
     * 
     */
    public function verifyEmail(Request $request)
    {
        $rules = [
            'user_id' => 'required',
            'key'     => 'required'
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $verification = EmailVerification::where('user_id', $request->user_id)
                                         ->where('key', $request->key)
                                         ->first();
        if( ! $verification ){
            return response([
                'error' => 'Invalid request'
            ], 400);
        }

        $verification->delete();

        $expiresAt = new Carbon($verification->expires_at);
        if( $expiresAt->format('U') <= now()->format('U') ){
            return response([
                'error' => 'Verification expired'
            ], 400);
        }

        $user = User::find($verification->user_id);
        if( ! $user ){
            return response([
                'error' => 'User no longer exists'
            ], 400);
        }

        $user->email_verified_at = now();
        $user->save();

        return response([
            'message' => 'Verified'
        ]);
    }
}
