<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\Role;
use \App\Models\User;
use \App\Models\UserCompany;
use \App\Models\UserInvite;
use Mail;

class UserInviteTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test inviting a user
     *
     * @group user-invites
     * 
     * @return void
     */
    public function testCreateUserInvite()
    {
        Mail::fake();

        $user = $this->createUser();

        $person = factory(\App\Models\User::class)->make();

        $role = Role::createReportingRole($this->account);

        $response = $this->json('POST', 'http://localhost/v1/user-invites', [
            'email'     => $person->email,
            'role'      => $role->id,
            'companies' => [$user->company->id],
        ], $this->authHeaders());

        $response->assertJson([
            'message' => 'success'
        ]);

        $response->assertStatus(200);

        Mail::assertQueued(\App\Mail\UserInvite::class);
    }

     /**
     * Test getting an invite 
     *
     * @group user-invites
     * 
     * @return void
     */
    public function testFetchInvite()
    {
        $user = $this->createUser();

        $role = Role::createReportingRole($this->account);

        $invite = factory(UserInvite::class)->create([
            'created_by' => $user->id,
            'role_id'    => $role->id,
            'companies'  => json_encode([$user->company->id]),
        ]);

        $response = $this->json('GET', 'http://localhost/v1/user-invites/' . $invite->id, [], $this->authHeaders());
        $response->assertStatus(200);

        $response->assertJson([
            'user_invite' => [
                'id'    => $invite->id,
                'email' => $invite->email
            ]
        ]);
    }


    /**
     * Test deleting an invite 
     *
     * @group user-invites
     * 
     * @return void
     */
    public function testDeleteUserInvite()
    {
        $user = $this->createUser();

        $role = Role::createReportingRole($this->account);

        $invite = factory(UserInvite::class)->create([
            'created_by' => $user->id,
            'role_id' => $role->id,
            'companies'  => json_encode([$user->company->id]),
        ]);

        $response = $this->json('DELETE', 'http://localhost/v1/user-invites/' . $invite->id, [], $this->authHeaders());

        $response->assertJson([
            'message' => 'success'
        ]);

        $response->assertStatus(200);

        $this->assertTrue(UserInvite::find($invite->id) == null);
    }

    /**
     * Test getting an invite as a public user
     *
     * @group user-invites
     * 
     * @return void
     */
    public function testFetchInviteAsPublicUser()
    {
        $user = $this->createUser();

        $role = Role::createReportingRole($this->account);

        $invite = factory(UserInvite::class)->create([
            'created_by' => $user->id,
            'role_id'    => $role->id,
            'companies'  => json_encode([$user->company->id]),
        ]);

        $response = $this->json('GET', 'http://localhost/v1/public/user-invites/' . $invite->id . '/' . $invite->key);
        $response->assertStatus(200);
        
        $response->assertJson([
            'user_invite' => [
                'id'    => $invite->id,
                'email' => $invite->email
            ]
        ]);
    }

    /**
     * Test getting an invite as a public user
     *
     * @group user-invites
     * 
     * @return void
     */
    public function testFetchInviteAsInvalidPublicUser()
    {
        $user = $this->createUser();

        $role = Role::createReportingRole($this->account);

        $invite = factory(UserInvite::class)->create([
            'created_by' => $user->id,
            'role_id'    => $role->id,
            'companies'  => json_encode([$user->company->id]),
        ]);

        $response = $this->json('GET', 'http://localhost/v1/public/user-invites/' . $invite->id . '/' . $invite->key . '2');
        $response->assertStatus(400);

        $response->assertJsonStructure([
            'error'
        ]);
    }

    /**
     * Test accepting an invite as a public user
     *
     * @group user-invites
     * 
     * @return void
     */
    public function testAcceptInviteAsPublicUser()
    {
        $user = $this->createUser();

        $role = Role::createReportingRole($this->account);

        $invite = factory(UserInvite::class)->create([
            'created_by' => $user->id,
            'role_id'    => $role->id,
            'companies'  => json_encode([$user->company->id]),
        ]);

        $user = factory(\App\Models\User::class)->make();

        $response = $this->json('PUT', 'http://localhost/v1/public/user-invites/' . $invite->id . '/' . $invite->key, [
            'first_name'    => $user->first_name,
            'last_name'     => $user->last_name,
            'email'         => $invite->email,
            'country_code'  => $user->country_code,
            'area_code'     => $user->area_code,
            'phone'         => $user->phone,
            'password'      => 'Password1!',
            'timezone'      => $user->timezone,
        ]);
 
        $response->assertStatus(200);
        
        $response->assertJson([
            'message' => 'success',
            'user' => [
                'email' => $invite->email
            ]
        ]);

        $this->assertTrue(UserInvite::find($invite->id) == null);

        $user = User::where('email', $invite->email)->first();
        $this->assertTrue($user != null);
        $this->assertTrue(count($user->companies) == 1);
        $this->assertTrue($user->companies[0]->id == $this->company->id);        
    }
}
