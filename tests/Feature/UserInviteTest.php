<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mail;

class UserInviteTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test inviting a user
     *
     * @group invite
     * 
     * @return void
     */
    public function testInviteUser()
    {
        Mail::fake();

        $user = $this->createUser();

        $person = factory(\App\Models\User::class)->make();

        $response = $this->json('POST', 'http://localhost/v1/invite', [
            'email' => $person->email
        ], $this->authHeaders());

        $response->assertJson([
            'message' => 'success',
            'ok'      => true
        ]);

        $response->assertStatus(200);

        Mail::assertQueued(\App\Mail\Auth\UserInvite::class);
    }
}
