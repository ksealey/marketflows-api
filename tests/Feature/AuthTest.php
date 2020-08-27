<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Mail\Auth\EmailVerification as EmailVerificationEmail;
use App\Models\Auth\EmailVerification;
use App\Mail\Auth\PasswordReset as PasswordResetEmail;
use App\Models\Account;
use App\Models\User;
use Faker\Generator as Faker;
use \Stripe\Stripe;
use \Stripe\Customer as StripeCustomer;
use \Stripe\PaymentMethod as StripePaymentMethod;
use App\Helpers\PaymentManager;
use Mail;

class AuthTest extends TestCase
{
    
    /**
     * Test creating an account successfully
     * 
     * @group auth
     */
    public function testRegister()
    {
        Mail::fake();

        $account = factory(Account::class)->make();
        $user    = factory(User::class)->make([
            'password'     => 'Password1!'
        ]);
        $paymentToken = 'tok_bypassPending';
        $customer     = new \stdClass();
        $customer->id = str_random(10);

        $paymentMethod = (object)[
            'id'   => str_random(10),
            'card' => (object)[
                'exp_year'  => now()->addYears(1)->format('Y'),
                'exp_month' => now()->format('m'),
                'last4'     => mt_rand(0001, 9999),
                'funding'   => 'credit',
                'brand'     => 'visa',
            ]
        ];

        $paymentMethods = (object)[
            'data' => [
                $paymentMethod
            ]
        ];

        $this->mock(PaymentManager::class, function($mock) use($account, $paymentToken, $customer, $paymentMethods){
            $mock->shouldReceive('createCustomer')
                 ->once()
                 ->with($account->name, $paymentToken)
                 ->andReturn($customer);

            $mock->shouldReceive('getPaymentMethods')
                 ->once()
                 ->with($customer->id)
                 ->andReturn($paymentMethods);
        });

        $response = $this->json('POST', route('auth-register'), [
            'payment_token' => $paymentToken,
            'account_name'  => $account->name,
            'first_name'    => $user->first_name,
            'last_name'     => $user->last_name,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'password'      => $user->password,
            'timezone'      => $user->timezone
        ]);
        $response->assertStatus(201);
        $response->assertJSON([
            "user" => [
                'role'          => $user->role,
                'timezone'      => $user->timezone,
                'first_name'    => $user->first_name,
                'email'         => $user->email,
                'phone'         => $user->phone
            ],
            "account" => [
                'name' => $account->name,
                'default_tts_voice' => $account->default_tts_voice,
                'default_tts_language' => $account->default_tts_language
            ],
            "first_login" => true
        ]);

        $account = $response['account'];

        $this->assertDatabaseHas('accounts', [
            'id' => $account['id']
        ]);

        $this->assertDatabaseHas('billing', [
            'account_id'  => $account['id'],
            'external_id' => $customer->id 
        ]);

        $this->assertDatabaseHas('payment_methods', [
            'account_id' => $account['id'],
            'created_by' => $response['user']['id'],
            'external_id'=> $paymentMethod->id
        ]);
        
        Mail::assertQueued(EmailVerificationEmail::class);
    }

     /**
     * Test creating an account with a spoof email domain fails
     * 
     * @group auth
     */
    public function testRegisterFailsWithSpoofEmailDomain()
    {
        Mail::fake();

        $account = factory(Account::class)->make();
        $user    = factory(User::class)->make([
            'account_name' => $account->name,
            'password'     => 'Password1!',
            'email'        => str_random(10) . '@' . config('app.spoof_email_domains')[0]
        ]);

        $response = $this->json('POST', route('auth-register'), [
            'payment_token' => 'tok_bypassPending',
            'account_name'  => $account->name,
            'first_name'    => $user->first_name,
            'last_name'     => $user->last_name,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'password'      => $user->password,
            'timezone'      => $user->timezone
        ]);
        $response->assertStatus(400);
        $response->assertJSONStructure([
            'error'
        ]);
        
        Mail::assertNotQueued(EmailVerificationEmail::class);
    }

    /**
     * Test creating a duplicate account fails
     * 
     * @group auth
     */
    public function testDuplicateRegistrationFails()
    {
        $account = factory(Account::class)->create();
        $user    = factory(User::class)->create([
            'account_id' => $account->id
        ]);

        $response = $this->json('POST', route('auth-register'), [
            'payment_token' => 'tok_bypassPending',
            'account_name'  => $account->name,
            'first_name'    => $user->first_name,
            'last_name'     => $user->last_name,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'password'      => $user->password,
            'timezone'      => $user->timezone
        ]);
        $response->assertStatus(400);
        $response->assertJSONStructure([
            'error'
        ]);
    }

    /**
     * Test invalid user is rejected
     * 
     * @group auth
     */
    public function testInvalidUserRejected()
    {
        $user = factory(User::class)->make();

        $response = $this->json('POST', route('auth-login'), [
            'email'    => $user->email,
            'password' => 'Password1!'
        ]);
        $response->assertStatus(400);
        $response->assertJSON([
            'error' => 'User not found'
        ]);
    }
    
    /**
     * Test successful login
     * 
     * @group auth
     */
    public function testSuccessfulLogin()
    {
        $account = factory(Account::class)->create();
        $user    = factory(User::class)->create([
            'account_id'     => $account->id,
            'last_login_at'  => null,
            'login_attempts' => 1,
            'password_reset_token' => str_random(128),
            'password_reset_expires_at' => now()->addDays(1)
        ]);

        $response = $this->json('POST', route('auth-login'), [
            'email'    => $user->email,
            'password' => 'password'
        ]);

        $response->assertStatus(200);

        $response->assertJSON([
            "user" => [
                'id'            => $user->id,
                'account_id'    => $user->account_id,
                'role'          => $user->role,
                'timezone'      => $user->timezone,
                'first_name'    => $user->first_name,
                'email'         => $user->email
            ],
            "account" => [
                'name' => $account->name,
                'default_tts_voice' => $account->default_tts_voice,
                'default_tts_language' => $account->default_tts_language
            ],
            "first_login" => true
        ]);

        //  Make sure attempts were reset
        $this->assertDatabaseHas('users', [
            'id'                    => $user->id,
            'login_attempts'        => 0,
            'password_reset_token'  => null,
            'password_reset_expires_at' => null
        ]);
    }

    /**
     * Test account disable user on 4th failed attempt
     * 
     * @group auth
     */
    public function testAccountDisabledAfterFailedAttempts()
    {
        $account = factory(Account::class)->create();
        $user    = factory(User::class)->create([
            'account_id' => $account->id,
        ]);

        for( $i = 0; $i < 3; $i++){
            $response = $this->json('POST', route('auth-login'), [
                'email'    => $user->email,
                'password' => 'invalid_password'
            ]);

            $response->assertStatus(400);
            $response->assertJSON([
                'error' => 'Password invalid'
            ]);
        }

        $response = $this->json('POST', route('auth-login'), [
            'email'    => $user->email,
            'password' => 'invalid_password'
        ]);
        $response->assertStatus(400);
        $response->assertJSON([
            'error' => 'Your account has been disabled for too many failed login attempts - you must reset your password to regain access'
        ]);

        $response = $this->json('POST', route('auth-login'), [
            'email'    => $user->email,
            'password' => 'invalid_password'
        ]);
        $response->assertStatus(403);
        $response->assertJSON([
            'error' => 'Login disabled'
        ]);
    }

    /**
     * Test login attempts reset
     * 
     * @group auth
     */
    public function testLoginAttemptsReset()
    {
        $account = factory(Account::class)->create();
        $user    = factory(User::class)->create([
            'account_id'     => $account->id,
            'login_attempts' => 2,
        ]);

        $response = $this->json('POST', route('auth-login'), [
            'email'    => $user->email,
            'password' => 'password'
        ]);

        $response->assertStatus(200);
        $user = User::find($user->id);

        $this->assertEquals($user->login_attempts, 0);
    }

    /**
     * Test resetting a password
     * 
     * @group auth
     */
    public function testPasswordResetSuccessfulFlow()
    {
        Mail::fake();

        $account = factory(Account::class)->create();
        $user    = factory(User::class)->create([
            'account_id'     => $account->id
        ]);

        $response = $this->json('POST', route('auth-request-reset-password'), [
            'email' => $user->email
        ]);

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'Sent'
        ]);

        Mail::assertQueued(PasswordResetEmail::class);
        

        $user = User::find($user->id);
        $this->assertNotNull($user->password_reset_token);
        $this->assertNotNull($user->password_reset_expires_at);

        //  
        //  Make sure we can now see the reset password
        //
        $response = $this->json('GET', route('auth-check-reset-password'), [
            'user_id' => $user->id,
            'token'   => $user->password_reset_token
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'Exists'
        ]);

        //  
        //  Use the password reset
        //
        $response = $this->json('POST', route('auth-handle-reset-password'), [
            'user_id' => $user->id,
            'token'   => $user->password_reset_token,
            'password' => 'Password1!'
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'Reset',
            'user' => [
                'id'            => $user->id,
                'account_id'    => $user->account_id,
                'role'          => $user->role,
                'timezone'      => $user->timezone,
                'first_name'    => $user->first_name,
                'email'         => $user->email
            ],
            'account' => [
                'name' => $account->name,
                'default_tts_voice' => $account->default_tts_voice,
                'default_tts_language' => $account->default_tts_language
            ],
            'first_login' => false
        ]);

        $user = User::find($user->id);
        $this->assertNull($user->password_reset_token);
        $this->assertNull($user->password_reset_expires_at);
    }

    /**
     * Test verifying email
     * 
     * @group auth
     */
    public function testVerifyEmail()
    {
        $account = factory(Account::class)->create();
        $user    = factory(User::class)->create([
            'account_id'     => $account->id,
            'email_verified_at' => null
        ]);

        $verification = EmailVerification::create([
            'user_id' => $user->id,
            'key'     => str_random(30),
            'expires_at' => now()->addHours(24)
        ]);

        $response = $this->json('POST', route('verify-email'), [
            'user_id' => $verification->user_id,
            'key'     => $verification->key
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'Verified'
        ]);

        $this->assertDatabaseMissing('email_verifications', [
            'user_id' => $user->id
        ]);

        $this->assertDatabaseMissing('users', [
            'id'                => $user->id,
            'email_verified_at' => null
        ]);
    }

    /**
     * Test verifying fails when invalid
     * 
     * @group auth
     */
    public function testVerifyEmailFailsWhenInvalid()
    {
        $account = factory(Account::class)->create();
        $user    = factory(User::class)->create([
            'account_id'     => $account->id,
            'email_verified_at' => null
        ]);

        $verification = EmailVerification::create([
            'user_id'    => $user->id,
            'key'        => str_random(30),
            'expires_at' => now()
        ]);

        $response = $this->json('POST', route('verify-email'), [
            'user_id' => $verification->user_id,
            'key'     => 'foo'
        ]);

        $response->assertStatus(400);
        $response->assertJSONStructure([
            'error'
        ]);

        $this->assertDatabaseHas('users', [
            'id'                => $user->id,
            'email_verified_at' => null
        ]);
    }

    /**
     * Test verifying fails when expired
     * 
     * @group auth
     */
    public function testVerifyEmailFailsWhenExpired()
    {
        $account = factory(Account::class)->create();
        $user    = factory(User::class)->create([
            'account_id'     => $account->id
        ]);

        $verification = EmailVerification::create([
            'user_id'    => $user->id,
            'key'        => str_random(30),
            'expires_at' => now()
        ]);

        $response = $this->json('POST', route('verify-email'), [
            'user_id' => $verification->user_id,
            'key'     => $verification->key
        ]);

        $response->assertStatus(400);
        $response->assertJSONStructure([
            'error'
        ]);

        $this->assertDatabaseMissing('email_verifications', [
            'user_id' => $user->id
        ]);

        $this->assertDatabaseHas('users', [
            'id'                => $user->id,
            'email_verified_at' => null
        ]);
    }
}

