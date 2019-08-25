<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Company;
use App\Models\UserCompany;

class UserTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test viewing a user
     *
     * @group users
     */
    public function testRead()
    {
        $user = $this->createUser();

        $otherUser = factory(User::class)->create([
            'account_id' => $user->account_id,
            'company_id' => $user->company_id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/users/' . $otherUser->id, [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJSON([
            'user' => [
                'id' => $otherUser->id
            ]
        ]);
    }

    /**
     * Test updating a user
     *
     * @group users
     */
    public function testUpdate()
    {
        $user = $this->createUser();

        $otherUser = factory(User::class)->create([
            'account_id' => $user->account_id,
            'company_id' => $this->company->id
        ]);

        $userCompany = UserCompany::create([
            'user_id'    => $otherUser->id,
            'company_id' => $this->company->id
        ]);

        $updatedUser = factory(User::class)->make();        

        $anotherCompany1 = factory(Company::class)->create([
            'account_id' => $user->account_id,
            'created_by' => $user->id,
            'name'       => 'Company B'
        ]);

        $anotherCompany2 = factory(Company::class)->create([
            'account_id' => $user->account_id,
            'created_by' => $user->id,
            'name'       => 'Company A'
        ]);

        $companyIds = [$anotherCompany1->id, $anotherCompany2->id];
        $response = $this->json('PUT', 'http://localhost/v1/users/' . $otherUser->id, [
            'first_name'   => $updatedUser->first_name,
            'last_name'    => $updatedUser->last_name,
            'email'        => $updatedUser->email,
            'country_code' => $updatedUser->country_code,
            'area_code'    => $updatedUser->area_code,
            'phone'        => $updatedUser->phone,
            'timezone'     => $updatedUser->timezone,
            'companies'    => $companyIds
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'user' => [
                'id'           => $otherUser->id,
                'first_name'   => $updatedUser->first_name,
                'last_name'    => $updatedUser->last_name,
                'email'        => $updatedUser->email,
                'country_code' => $updatedUser->country_code,
                'area_code'    => $updatedUser->area_code,
                'phone'        => $updatedUser->phone,
                'timezone'     => $updatedUser->timezone,
            ]
        ]);

        $user = User::find($otherUser->id);
        $this->assertTrue($user != null);
        $this->assertTrue($user->company_id == $anotherCompany2->id);
        $this->assertTrue(UserCompany::find($userCompany->id) == null);

        $userCompanies = UserCompany::where('user_id', $otherUser->id)
                                    ->orderBy('id', 'ASC')
                                    ->get();
        $this->assertTrue(count($userCompanies) == 2);
        $this->assertTrue(in_array($userCompanies[0]->company_id, $companyIds));
        $this->assertTrue(in_array($userCompanies[1]->company_id, $companyIds));
    }

    /**
     * Test viewing a user
     *
     * @group users
     */
    public function testDelete()
    {
        $user = $this->createUser();

        $otherUser = factory(User::class)->create([
            'account_id' => $user->account_id,
            'company_id' => $user->company_id
        ]);

        $response = $this->json('DELETE', 'http://localhost/v1/users/' . $otherUser->id, [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'deleted'
        ]);
    }

    /**
     * Test changing a password
     *
     * @group users
     */
    public function testChangePassword()
    {
        $user = $this->createUser();

        $otherUser = factory(User::class)->create([
            'account_id' => $user->account_id,
            'company_id' => $user->company_id
        ]);

        $response = $this->json('PUT', 'http://localhost/v1/users/' . $otherUser->id . '/change-password', [
            'password' => 'Password1!'
        ], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'success'
        ]);
    }

    /**
     * Test changing your own password
     *
     * @group users
     */
    public function testChangeOwnPassword()
    {
        $user = $this->createUser();

        $response = $this->json('PUT', 'http://localhost/v1/users/' . $user->id . '/change-password', [
            'password' => 'Password1!'
        ], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'success',
            'user' => [
                'id' => $user->id,
            ],
            'auth_token' => User::find($user->id)->auth_token
        ]);
    }
}
