<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Billing;
use App\Models\User;
use App\Models\Company\PhoneNumber;
use App\Models\PaymentMethod;
use App\Models\Auth\EmailVerification;
use App\Helpers\PaymentManager;
use App\Mail\Auth\EmailVerification as UserEmailVerificationMail;
use \App\Rules\CountryRule;
use \App\Rules\UniqueEmailRule;
use \Carbon\Carbon;
use Validator;
use DB;
use Exception;
use Mail;
use Log;

class RegisterController extends Controller
{
    protected $paymentManager;

    public function __construct(PaymentManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
    }
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
            'email'                 => ['bail', 'required', 'email', 'max:128', new UniqueEmailRule(null)],
            'phone'                 => ['bail','required', 'regex:/(.*)[0-9]{3}(.*)[0-9]{3}(.*)[0-9]{4}/'],
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
            $customer = $this->paymentManager->createCustomer(
                $request->account_name,
                $request->payment_token
            );
            
            $paymentMethods = $this->paymentManager->getPaymentMethods($customer->id);
            $paymentMethod  = $paymentMethods->data[0];
            $card           = $paymentMethod->card;

            //  Create account
            $account = Account::create([
                'name'                          => $request->account_name,
                'tts_voice'                     => 'Joanna',
                'tts_language'                  => 'en-US',
                'source_param'                  => 'utm_source,source',
                'medium_param'                  => 'utm_medium,medium',
                'content_param'                 => 'utm_content,content',
                'campaign_param'                => 'utm_campaign,content',
                'keyword_param'                 => 'utm_term,term,keyword',
                'source_referrer_when_empty'    => 1
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
                'phone'                     => PhoneNumber::countryCode($request->phone) . PhoneNumber::number($request->phone),
                'password_hash'             => bcrypt($request->password),
                'auth_token'                => str_random(255),
                'last_login_at'             => now()
            ]);

            //  Add initial payment method
            $expiration = new Carbon($card->exp_year . '-' . $card->exp_month . '-01 00:00:00'); 
            $expiration->endOfMonth();
            PaymentMethod::create([
                'account_id'     => $user->account_id,
                'created_by'     => $user->id,
                'external_id'    => $paymentMethod->id,
                'last_4'         => $card->last4,
                'type'           => $card->funding,
                'brand'          => ucfirst($card->brand),
                'expiration'     => $expiration->format('Y-m-d'),
                'primary_method' => true
            ]);

            //  Add verification mail to queue
            Mail::to($user->email)
                ->queue(new UserEmailVerificationMail($user));
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

    public function emailAvailability(Request $request)
    {
        $validator = Validator::make($request->input(), [
            'email' => 'required|email|max:128',
        ]);

        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400); 

        $available = User::where('email', $request->email)->count() == 0;

        return response([
            'available' => $available
        ]);  
    }
}
