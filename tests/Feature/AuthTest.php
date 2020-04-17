<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Account;
use App\Models\User;
use Faker\Generator as Faker;

class AuthTest extends TestCase
{
    
    /**
     * Test creating an account successfully
     * 
     * @group auth
     */
    public function testRegister()
    {
        $account = factory(Account::class)->make();
        $user    = factory(User::class)->make([
            'account_name' => $account->name,
            'plan'         => $account->plan,
            'password'     => 'Password1!'
        ])->toArray();

        $response = $this->json('POST', route('auth-register'), $user);
        $response->assertStatus(201);
        $response->assertJSONStructure([
            "auth_token",
            "user" => [
                "id",
                "account_id",
                "role_id",
                "timezone",
                "first_name",
                "email"
            ],
            "account" => [
                "name",
                "plan",
                "balance",
                "auto_reload_minimum",
                "auto_reload_amount",
                "default_tts_voice",
                "default_tts_language",
                "link",
                "kind"
            ],
            "first_login"
        ]);
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
}
