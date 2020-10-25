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
use App\Models\Auth\PaymentSetup;
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
            'intent_id'             => 'bail|required',
            'intent_client_secret'  => 'bail|required',
            'payment_method_id'     => 'bail|required',
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

        $paymentSetup = PaymentSetup::where('email', $request->email)
                                    ->where('expires_at', '>=', now())
                                    ->where('intent_id', $request->intent_id)
                                    ->where('intent_client_secret', $request->intent_client_secret)
                                    ->first();
        if( ! $paymentSetup ){
            return response([
                'error' => 'No payment setup not found'
            ], 400);
        }

        DB::beginTransaction();

        try{
            $paymentMethods = $this->paymentManager
                                   ->getPaymentMethods($paymentSetup->customer_id);

            $paymentMethod = null;
            foreach( $paymentMethods as $pm ){
                if( $request->payment_method_id == $pm->id )
                    $paymentMethod = $pm;
            }
            $card = $paymentMethod->card;

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
                'billing_period_ends_at'    => now()->addDays(7)->endOfDay(),
                'external_id'               => $paymentSetup->customer_id
            ]);

            //  Create this user
            $user = User::create([
                'account_id'                => $account->id,
                'role'                      => User::ROLE_ADMIN,
                'timezone'                  => $request->timezone,
                'first_name'                => ucfirst(strtolower($request->first_name)),
                'last_name'                 => ucfirst(strtolower($request->last_name)),
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

            $paymentSetup->delete();
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
            'expires_at' => now()->addHours(1)
        ]);

        Mail::to($emailVerification->email)
            ->queue(new EmailVerificationMail($emailVerification));
        
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

    public function paymentSetup(Request $request)
    {
        $validator = Validator::make($request->input(), [
            'email' => ['bail', 'required', 'email', 'max:128', new UniqueEmailRule(null)]
        ]);

        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400); 

        $existingSetup = PaymentSetup::where('email', $request->email)->first();
        if( $existingSetup ){
            $existingSetup->delete();
            $this->paymentManager
                 ->deleteCustomer($existingSetup->customer_id);
        }

        $customer = $this->paymentManager
                         ->createCustomer($request->email);

        $intent = $this->paymentManager
                       ->createSetupIntent($customer->id);

        $setup = PaymentSetup::create([
            'customer_id'   => $customer->id,
            'intent_id'     => $intent->id,
            'intent_client_secret' => $intent->client_secret,
            'email'       => $request->email,
            'expires_at'  => now()->addHours(1)
        ]);

        $setup->intent = $intent;

        return response($setup, 201);
    }
}
