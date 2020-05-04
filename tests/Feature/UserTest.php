<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use \App\Models\User;
use \App\Models\Company;
use \App\Mail\AddUser as AddUserEmail;
use App\Mail\Auth\EmailVerification as UserEmailVerificationMail;

use Mail;

class UserTest extends TestCase
{
   use \Tests\CreatesAccount;

   /**
    *   Test listing users 
    *
    *   @group users
    */
    public function testListingUsers()
    {
        $users = factory(User::class, 10)->create([
            'account_id' => $this->account->id,
        ]);

        $response = $this->json('GET', route('list-users'));

        $response->assertStatus(200);
        $response->assertJSON([
            'result_count' => 10,
            'limit' => 250,
            'page' => 1,
            'total_pages' => 1,
            'next_page' => null,
            'results' => []
        ]);
    }

   /**
     * Test adding a admin user
     * 
     * @group users
     */
    public function testAddingAdminUser()
    {
        Mail::fake();

        $user = factory(User::class)->make(); 

        $response = $this->json('POST', route('create-user') , [
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->email,
            'timezone'   => $user->timezone,
            'role'       => User::ROLE_ADMIN
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->email,
            'timezone'   => $user->timezone,
            'role'       => User::ROLE_ADMIN
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $response['id']
        ]);

        $this->assertDatabaseMissing('user_companies', [
            'user_id' => $response['id']
        ]);

        Mail::assertSent(AddUserEmail::class);
    }

    /**
     * Test adding a system user
     * 
     * @group users
     */
    public function testAddingSystemUser()
    {
        Mail::fake();

        $user = factory(User::class)->make(); 

        $response = $this->json('POST', route('create-user') , [
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->email,
            'timezone'   => $user->timezone,
            'role'       => User::ROLE_SYSTEM
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->email,
            'timezone'   => $user->timezone,
            'role'       => User::ROLE_SYSTEM
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $response['id']
        ]);

        $this->assertDatabaseMissing('user_companies', [
            'user_id' => $response['id']
        ]);

        Mail::assertSent(AddUserEmail::class);
    }

    /**
     * Test adding a reporting user
     * 
     * @group users
     */
    public function testAddingReportingUser()
    {
        Mail::fake();

        $user    = factory(User::class)->make(); 
        $company = factory(Company::class)->create([
            'account_id' => $this->user->account_id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('POST', route('create-user') , [
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->email,
            'timezone'   => $user->timezone,
            'role'       => User::ROLE_REPORTING,
            'companies' => json_encode([$company->id])
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->email,
            'timezone'   => $user->timezone,
            'role'       => User::ROLE_REPORTING
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $response['id']
        ]);

        $this->assertDatabaseHas('user_companies', [
            'user_id'   => $response['id'],
            'company_id' => $company->id            
        ]);

        Mail::assertSent(AddUserEmail::class);
    }

    /**
     * Test adding a reporting user
     * 
     * @group users
     */
    public function testAddingClientUser()
    {
        Mail::fake();

        $user    = factory(User::class)->make(); 
        $company = factory(Company::class)->create([
            'account_id' => $this->user->account_id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('POST', route('create-user') , [
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->email,
            'timezone'   => $user->timezone,
            'role'       => User::ROLE_CLIENT,
            'companies' => json_encode([$company->id])
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->email,
            'timezone'   => $user->timezone,
            'role'       => User::ROLE_CLIENT
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $response['id']
        ]);

        $this->assertDatabaseHas('user_companies', [
            'user_id'   => $response['id'],
            'company_id' => $company->id            
        ]);

        Mail::assertSent(AddUserEmail::class);
    }

    /**
     * Test veiwing a user
     * 
     * @group users
     */
    public function testReadUser()
    {
        $user = factory(User::class)->create([
            'account_id' => $this->account->id
        ]); 
        
        $response = $this->json('GET', route('read-user', [
            'user' => $user->id
        ]));

        $response->assertJSON([
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->email,
            'timezone'   => $user->timezone,
            'role'       => $user->role
        ]);
    }

    /**
     * Test updating a user
     * 
     * @group users
     */
    public function testUpdateUser()
    {
        Mail::fake();

        $originalUser = factory(User::class)->create([
            'account_id' => $this->account->id
        ]); 

        $user = factory(User::class)->create([
            'account_id' => $this->account->id
        ]);
        
        $response = $this->json('PUT', route('update-user', [
            'user' => $originalUser->id
        ]), $user->toArray());

        $response->assertJSON([
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->email,
            'timezone'   => $user->timezone,
            'role'       => $user->role
        ]);

        Mail::assertSent(UserEmailVerificationMail::class);
    }

    /**
     * Test deleting a user
     * 
     * @group users
     */
    public function testDeleteUser()
    {
        $user = factory(User::class)->create([
            'account_id' => $this->account->id
        ]);
        
        $response = $this->json('DELETE', route('delete-user', [
            'user' => $user->id
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'deleted'
        ]);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
            'deleted_at' => null
        ]);
    }

    /**
     * Test system users cannot delete other users
     * 
     * @group users
     */
    public function testSystemUsersCannotDeleteUsers()
    {
        $this->user = factory(User::class)->create([
            'account_id' => $this->account->id,
            'role' => User::ROLE_SYSTEM
        ]);

        $otherUser = factory(User::class)->create([
            'account_id' => $this->account->id
        ]);

        $response = $this->json('DELETE', route('delete-user', [
            'user' => $otherUser->id
        ]));

        $response->assertStatus(403); //  Persmission denied
    }

    /**
     * Test reporting users cannot delete other users
     * 
     * @group users
     */
    public function testReportingUsersCannotDeleteUsers()
    {
        $this->user = factory(User::class)->create([
            'account_id' => $this->account->id,
            'role' => User::ROLE_REPORTING
        ]);

        $otherUser = factory(User::class)->create([
            'account_id' => $this->account->id
        ]);

        $response = $this->json('DELETE', route('delete-user', [
            'user' => $otherUser->id
        ]));

        $response->assertStatus(403); //  Persmission denied
    }

    /**
     * Test client users cannot delete other users
     * 
     * @group users
     */
    public function testClientUsersCannotDeleteUsers()
    {
        $this->user = factory(User::class)->create([
            'account_id' => $this->account->id,
            'role' => User::ROLE_CLIENT
        ]);

        $otherUser = factory(User::class)->create([
            'account_id' => $this->account->id
        ]);

        $response = $this->json('DELETE', route('delete-user', [
            'user' => $otherUser->id
        ]));

        $response->assertStatus(403); //  Persmission denied
    }
}
