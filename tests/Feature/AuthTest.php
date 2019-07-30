<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use \App\Models\User;

class AuthTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * A basic feature test example.
     *
     * @group auth
     * 
     * @return void
     */
    public function testRegisterUser()
    {
        Mail::fake();

        $company = factory(\App\Models\Company::class)->make();
        $user    = factory(\App\Models\User::class)->make();

        $data = [
            'company_name' => $company->name,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'email'        => $user->email,
            'country_code' => $user->country_code,
            'area_code'    => $user->area_code,
            'phone'        => $user->phone,
            'timezone'     => $user->timezone,
            'password'     => 'Password1!'
        ];

        $response = $this->json('POST', 'http://localhost/v1/auth/register', $data);

        $response->assertJson([
            'ok'      => true,
            'message' => 'created',
        ]);

        $response->assertJsonStructure([
            'ok',
            'message',
            'user',
            'refresh_token',
            'bearer_token'
        ]);

        $response->assertStatus(201);

        //  Test Email verification sent
        Mail::assertQueued(\App\Mail\Auth\EmailVerification::class);
    }

    /**
     * Test a successful login
     * 
     * @group auth
     * 
     * @return void
     */
    public function testSuccessfulLogin()
    {
        $user = $this->createUser();

        $response = $this->json('POST', 'https://localhost/v1/auth/login', [
            'email'     => $user->email,
            'password'  => 'password'
        ]);

        $response->assertJson([
            'ok'      => true,
            'message' => 'success',
        ]);

        $response->assertJsonStructure([
            'ok',
            'message',
            'user',
            'refresh_token',
            'bearer_token'
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test refreshing a token
     * 
     * @group auth
     * 
     * @return void
     */
    public function testRefreshToken()
    {
        $user = $this->createUser();

        $response = $this->json('POST', 'https://localhost/v1/auth/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $user->getRefreshToken()
        ]);

        $response->assertJson([
            'ok'      => true,
            'message' => 'success',
        ]);

        $response->assertJsonStructure([
            'ok',
            'message',
            'bearer_token'
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test refreshing a token
     * 
     * @group auth
     * 
     * @return void
     */
    public function testRefreshTokenFailsFromBlackist()
    {
        $user = $this->createUser();

        $refreshToken     = $user->getRefreshToken();
        $blacklistedToken = factory(\App\Models\Auth\BlacklistedToken::class)->create([
            'token' => $refreshToken
        ]);

        $response = $this->json('POST', 'https://localhost/v1/auth/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken
        ]);

        $response->assertJson([
            'ok'    => false,
            'error' => 'Unable to generate token',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test a failed login
     * 
     * @group auth
     * 
     * @return void
     */
    public function testFailedLogin()
    {
        $user = $this->createUser();

        //  Wrong email
        $response = $this->json('POST', 'https://localhost/v1/auth/login', [
            'email'     => $user->email . '_dne',
            'password'  => 'password'
        ]);
        $response->assertJson([
            'ok'      => false,
            'error'  => 'User does not exist',
        ]);
        $response->assertStatus(400);

        //  Wrong password
        $response = $this->json('POST', 'https://localhost/v1/auth/login', [
            'email'     => $user->email,
            'password'  => 'password1'
        ]);
        $response->assertJson([
            'ok'      => false,
            'error'  => 'Invalid credentials',
        ]);
        $response->assertStatus(400);
        
        //  Again...
        $response = $this->json('POST', 'https://localhost/v1/auth/login', [
            'email'     => $user->email,
            'password'  => 'password1'
        ]);
        $response->assertJson([
            'ok'      => false,
            'error'  => 'Invalid credentials',
        ]);
        $response->assertStatus(400);
        
        //  And again...
        $response = $this->json('POST', 'https://localhost/v1/auth/login', [
            'email'     => $user->email,
            'password'  => 'password1'
        ]);
        $response->assertJson([
            'ok'      => false,
            'error'  => 'Invalid credentials',
        ]);
        $response->assertStatus(400);
        
        //  And lock account...
        $response = $this->json('POST', 'https://localhost/v1/auth/login', [
            'email'     => $user->email,
            'password'  => 'password1'
        ]);
        $response->assertJson([
            'ok'      => false,
            'error'  => 'Too many failed attempts - account disabled for 8 hours',
        ]);
        $response->assertStatus(400);

        //  And make sure user can't login...
        $response = $this->json('POST', 'https://localhost/v1/auth/login', [
            'email'     => $user->email,
            'password'  => 'password1'
        ]);
        $response->assertSee('Account disabled until');
        $response->assertStatus(400);

        //  Even with valid credentials...
        $response = $this->json('POST', 'https://localhost/v1/auth/login', [
            'email'     => $user->email,
            'password'  => 'password'
        ]);
        $response->assertSee('Account disabled until');
        $response->assertStatus(400);
    }

    /**
     * Test a login attempts reset on valid login
     * 
     * @group auth
     * 
     * @return void
     */
    public function testLoginAttemptsReset()
    {
        $user = $this->createUser(); 

        //  Wrong password
        $response = $this->json('POST', 'https://localhost/v1/auth/login', [
            'email'     => $user->email,
            'password'  => 'password1'
        ]);
        $response->assertJson([
            'ok'      => false,
            'error'  => 'Invalid credentials',
        ]);
        $response->assertStatus(400);

        $u = \App\Models\User::find($user->id);
        $this->assertTrue($u->login_attempts == 1);
        $this->assertTrue($u->last_login_at == null);
        
        //  Right password
        $response = $this->json('POST', 'https://localhost/v1/auth/login', [
            'email'     => $user->email,
            'password'  => 'password'
        ]);
        $response->assertJson([
            'ok' => true
        ]);
        $response->assertStatus(200);

        $u = \App\Models\User::find($user->id);
        $this->assertTrue($u->login_attempts == 0);
        $this->assertTrue($u->last_login_at != null);
    }

    /**
     * Test requesting a password reset
     * 
     */
    public function testRequestPasswordReset()
    {
        Mail::fake();

        $user = $this->createUser();

        $response = $this->json('POST', 'http://localhost/v1/auth/reset-password', [
            'email' => $user->email
        ]);

        //  Make sure the request is ok
        $response->assertJson([
            'ok' => true,
            'message' => 'success'
        ]);

        $response->assertStatus(200);

        //  Make sure the password reset is created
        $passwordReset = \App\Models\Auth\PasswordReset::where('user_id', $user->id)->first();
        $this->assertTrue($passwordReset != null);

        //  Make sure the email was sent out
        Mail::assertQueued(\App\Mail\Auth\PasswordReset::class);

        return $passwordReset;
    }

    /**
     * Test resetting the password from password reset
     * 
     * @depends testRequestPasswordReset
     */
    public function testGetResettingPassword($passwordReset)
    {
        $response = $this->get('http://localhost/auth/reset-password/' . $passwordReset->user_id . '/' . $passwordReset->key);

        $response->assertStatus(200);

        $user            = User::find($passwordReset->user_id);
        $currentPassword = $user->password_hash;

        $response = $this->post('http://localhost/auth/reset-password/' . $passwordReset->user_id . '/' . $passwordReset->key, [
            'password' => 'Password1!'
        ]);
        $response->assertStatus(200);

        //  Make sure my password was reset
        $user = User::find($passwordReset->user_id);
        $this->assertTrue($currentPassword != $user->password_hash);

        //  Make sure the password reset was deleted
        $this->assertTrue(\App\Models\Auth\PasswordReset::find($passwordReset->id) == null);
    }


    
}
