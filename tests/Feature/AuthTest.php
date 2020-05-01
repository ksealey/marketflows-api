<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Mail\Auth\EmailVerification as EmailVerificationEmail;
use App\Mail\Auth\PasswordReset as PasswordResetEmail;
use App\Models\Account;
use App\Models\User;
use App\Models\Auth\PasswordReset;
use Faker\Generator as Faker;
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
            'account_name' => $account->name,
            'account_type' => $account->account_type,
            'password'     => 'Password1!'
        ]);

        $response = $this->json('POST', route('auth-register'), $user->toArray());
        $response->assertStatus(201);
        $response->assertJSON([
            "user" => [
                'role'          => $user->role,
                'timezone'      => $user->timezone,
                'first_name'    => $user->first_name,
                'email'         => $user->email
            ],
            "account" => [
                'name' => $account->name,
                'account_type' => $account->account_type,
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
            'account_id' => $account['id']
        ]);
        
        Mail::assertQueued(EmailVerificationEmail::class);
    }

    /**
     * Test creating a duplicate account faile
     * 
     * @group auth
     */
    public function testDuplicateRegistrationFails()
    {
        $account = factory(Account::class)->create();
        $user    = factory(User::class)->create([
            'account_id' => $account->id
        ])->toArray();

        $response = $this->json('POST', route('auth-register'), array_merge($user, [
            'account_name' => $account->name,
            'plan'         => $account->plan,
            'password'     => 'Password1!'
        ]));
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
            'error' => 'User does not exist'
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
            'account_id' => $account->id,
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
                'account_type' => $account->account_type,
                'default_tts_voice' => $account->default_tts_voice,
                'default_tts_language' => $account->default_tts_language
            ],
            "first_login" => false
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
                'password' => 'invalid password'
            ]);

            $response->assertStatus(400);
            $response->assertJSON([
                'error' => 'Invalid credentials'
            ]);
        }

        $response = $this->json('POST', route('auth-login'), [
            'email'    => $user->email,
            'password' => 'invalid password'
        ]);
        $response->assertStatus(400);
        $response->assertJSON([
            'error' => 'Too many failed attempts - account disabled for 4 hours',
        ]);

        $response = $this->json('POST', route('auth-login'), [
            'email'    => $user->email,
            'password' => 'invalid password'
        ]);

        $response->assertStatus(400);
        $response->assertJSON([
            'error' => 'Account disabled for the next 4 hours - try again later'
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
        $account = factory(Account::class)->create();
        $user    = factory(User::class)->create([
            'account_id'     => $account->id
        ]);

        Mail::fake();

        $response = $this->json('POST', route('auth-request-reset-password'), [
            'email' => $user->email
        ]);

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'sent'
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
            'message' => 'exists'
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
            'message' => 'reset',
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
                'account_type' => $account->account_type,
                'default_tts_voice' => $account->default_tts_voice,
                'default_tts_language' => $account->default_tts_language
            ],
            'first_login' => false
        ]);

        $user = User::find($user->id);
        $this->assertNull($user->password_reset_token);
        $this->assertNull($user->password_reset_expires_at);
    }
}

