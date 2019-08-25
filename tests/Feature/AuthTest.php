<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use \App\Models\Account;
use \App\Models\Company;
use \App\Models\AccountCompany;
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

        // Make sure the request was successful
        $response->assertJsonStructure([
            'message',
            'auth_token',
            'user' => [
                'id',
                'created_at'
            ]
        ]);
        
        $response->assertStatus(201);

        //  Make sure the account, company and user was created
        $user = User::find(json_decode($response->getContent())->user->id);
        $this->assertTrue( $user != null);
        $this->assertTrue( $user->account != null );
        $this->assertTrue( $user->company != null );
        $this->assertTrue(count($user->companies) == 1);
        $this->assertTrue($user->companies[0]->id == $user->company->id);   

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

        $response->assertJsonStructure([
            'message',
            'user',
            'auth_token'
        ]);

        $response->assertStatus(200);
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
            'error'  => 'User does not exist',
        ]);
        $response->assertStatus(400);

        //  Wrong password
        $response = $this->json('POST', 'https://localhost/v1/auth/login', [
            'email'     => $user->email,
            'password'  => 'password1'
        ]);
        $response->assertJson([
            'error'  => 'Invalid credentials',
        ]);
        $response->assertStatus(400);
        
        //  Again...
        $response = $this->json('POST', 'https://localhost/v1/auth/login', [
            'email'     => $user->email,
            'password'  => 'password1'
        ]);
        $response->assertJson([
            'error'  => 'Invalid credentials',
        ]);
        $response->assertStatus(400);
        
        //  And again...
        $response = $this->json('POST', 'https://localhost/v1/auth/login', [
            'email'     => $user->email,
            'password'  => 'password1'
        ]);
        $response->assertJson([
            'error'  => 'Invalid credentials',
        ]);
        $response->assertStatus(400);
        
        //  And lock account...
        $response = $this->json('POST', 'https://localhost/v1/auth/login', [
            'email'     => $user->email,
            'password'  => 'password1'
        ]);
        $response->assertJson([
            'error'  => 'Too many failed attempts - account disabled for 8 hours',
        ]);
        $response->assertStatus(400);

        //  And make sure user can't login...
        $response = $this->json('POST', 'https://localhost/v1/auth/login', [
            'email'     => $user->email,
            'password'  => 'password1'
        ]);
        $response->assertSee('Account disabled -');
        $response->assertStatus(400);

        //  Even with valid credentials...
        $response = $this->json('POST', 'https://localhost/v1/auth/login', [
            'email'     => $user->email,
            'password'  => 'password'
        ]);
        $response->assertSee('Account disabled -');
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
       
        $response->assertStatus(200);

        $u = \App\Models\User::find($user->id);
        $this->assertTrue($u->login_attempts == 0);
        $this->assertTrue($u->last_login_at != null);
    }

    /**
     * Test requesting a password reset
     * 
     * @group auth
     */
    public function testRequestPasswordReset()
    {
        Mail::fake();

        $user = $this->createUser();

        $response = $this->json('POST', 'http://localhost/v1/auth/reset-password', [
            'email' => $user->email
        ]);

        //  Make sure the request is ok
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
     * @group auth
     */
    public function testGetResettingPassword($passwordReset)
    {
        $user              = User::find($passwordReset->user_id);
        $user->disabled_until = date('Y-m-d H:i:s', strtotime('tomorrow'));
        $user->save();

        $currentPassword = $user->password_hash;

        $response = $this->post('http://localhost/v1/auth/reset-password/' . $passwordReset->user_id . '/' . $passwordReset->key, [
            'password' => 'Password1!'
        ]);

        //  Make sure response is ok
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'user',
            'auth_token'
        ]);

        //  Make sure user options were reset
        $userData = json_decode($response->getContent());
        $this->assertTrue($userData->auth_token != $user->auth_token);

        //  Make sure my password was reset
        $user = User::find($passwordReset->user_id);
        $this->assertTrue($currentPassword != $user->password_hash);

        //  Make sure the password reset was deleted
        $this->assertTrue(\App\Models\Auth\PasswordReset::find($passwordReset->id) == null);
    }


    
}
