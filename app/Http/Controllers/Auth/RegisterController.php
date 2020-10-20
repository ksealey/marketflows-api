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
use App\Mail\Auth\EmailVerification as EmailVerificationMail;
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
        //  Make sure the email was verified
        //
        $emailVerification = EmailVerification::where('email', $request->email)
                                              ->whereNotNull('verified_at')
                                              ->first();
        if( ! $emailVerification ){
            return response([
                'error' => 'You must verify your email address before creating an account'
            ], 400);
        }
        

        //
        //  Try to add customer with payment method
        //
        $customer = null;
        $card     = null;

        try{
            $customer = $this->paymentManager->createCustomer(
                $request->account_name,
                $request->payment_token
            );
        }catch(\Stripe\Exception\CardException $e){
            return response([
                'error' => $e->getMessage()
            ], 400);
        }catch(\Stripe\Exception\RateLimitException $e){
            return response([
                'error' => 'We can\'t complete this request right now. Please try again shortly.'
            ], 400);
        }

        DB::beginTransaction();

        try{
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
                'billing_period_ends_at'    => now()->addDays(7)->endOfDay(), // End of day, 30 days from now,
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

    public function requestEmailVerification(Request $request)
    {
        $validator = Validator::make($request->input(), [
            'email' => ['bail', 'required', 'email', 'max:128', new UniqueEmailRule(null)],
        ]);

        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400); 

        EmailVerification::where('email', $request->email)->delete();

        $emailVerification = EmailVerification::create([
            'email'      => $request->email,
            'code'       => mt_rand(100000, 999999),
            'expires_at' => now()->addMinutes(3)
        ]);

        Mail::queue(new EmailVerificationMail($emailVerification));
        
        return response([
            'message' => 'Sent'
        ]);  
    }

    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->input(), [
            'email' => ['bail', 'required', 'email', 'max:128', new UniqueEmailRule(null)],
            'code'  => 'bail|required|numeric|digits:6'
        ]);

        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400); 

        $emailVerification = EmailVerification::where('email', $request->email)
                                              ->whereNull('verified_at')
                                              ->first();
        if( ! $emailVerification ){
            return response([
                'error' => 'No verifications for this email address found'
            ], 400);
        }

        if( $emailVerification->code == $request->code ){
            $emailVerification->verified_at = now();
            $emailVerification->save();

            return response([
                'message' => 'Verified'
            ]);
        }

        $emailVerification->failed_attempts++;
        if( $emailVerification->failed_attempts >= 3 ){
            $emailVerification->delete();

            return response([
                'error' => 'Too many failed attempts. You must request verification again.'
            ], 400);
        }

        $emailVerification->save();
        return response([
            'error' => 'Code invalid'
        ], 400);
    }
}
