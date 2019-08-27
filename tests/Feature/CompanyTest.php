<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\Company;
use \App\Models\UserCompany;

class CompanyTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test listing records
     * 
     * @group companies
     */
    public function testList()
    {
        $user = $this->createUser();

        $companies = [];
        for($i = 0; $i < 3; $i++){
            $companies[] = factory(Company::class)->create([
                'account_id' => $user->account_id
            ]);
        }

        $response = $this->json('GET', 'http://localhost/v1/companies', [], $this->authHeaders());
        $response->assertStatus(200);
        
        $response->assertJson([
            'result_count' => count($companies) + 1, // Add one for the default company created
            'limit'        => 25,
            'page'         => 1,
            'total_pages'  => 1,
            'companies'    => [
                ['id' => $user->company_id],
                ['id' => $companies[0]->id],
                ['id' => $companies[1]->id],
                ['id' => $companies[2]->id]
            ]
        ]);
    }

    /**
     * Test listing records with a filter
     * 
     * @group companies
     */
    public function testListWithFilter()
    {
        $user = $this->createUser();

        $companies = [];
        for($i = 0; $i < 3; $i++){
            $companies[] = factory(Company::class)->create([
                'account_id' => $user->account_id
            ]);
        }

        $response = $this->json('GET', 'http://localhost/v1/companies', [
            'search' => $companies[0]->name,
            'limit'  => 5,
            'page'   => 1
        ], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJson([
            'result_count' => 1, // Add one for the default company created
            'limit'        => 5,
            'page'         => 1,
            'total_pages'  => 1,
            'companies'    => [
                ['id' => $companies[0]->id]
            ]
        ]);
    }

    /**
     * Test creating a record
     *
     * @group companies
     */
    public function testCreate()
    {
        $user    = $this->createUser();
        $company = factory(Company::class)->make();

        $response = $this->json('POST', 'http://localhost/v1/companies', [
            'name' => $company->name
        ], $this->authHeaders());

        $response->assertStatus(201);

        $response->assertJSON([
            'message' => 'created',
            'company' => [
                'name' => $company->name
            ]
        ]);
    }

    /**
     * Test reading a record
     *
     * @group companies
     */
    public function testRead()
    {
        $user    = $this->createUser();
        $company = factory(Company::class)->create([
            'account_id' => $user->account_id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/companies/' . $company->id, [
            'name' => $company->name
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'success',
            'company' => [
                'name' => $company->name
            ]
        ]);
    }

    /**
     * Test updating a record
     *
     * @group companies
     */
    public function testUpdate()
    {
        $user    = $this->createUser();
        $company = factory(Company::class)->create([
            'account_id' => $user->account_id
        ]);

        $updatedCompany = factory(Company::class)->make();
        $response = $this->json('PUT', 'http://localhost/v1/companies/' . $company->id, [
            'name' => $updatedCompany->name
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'success',
            'company' => [
                'id'   => $company->id,
                'name' => $updatedCompany->name
            ]
        ]);
    }

    /**
     * Test deleting a record
     *
     * @group companies
     */
    public function testDelete()
    {
        $user    = $this->createUser();
        $company = factory(Company::class)->create([
            'account_id' => $user->account_id
        ]);

        $updatedCompany = factory(Company::class)->make();
        $response = $this->json('DELETE', 'http://localhost/v1/companies/' . $company->id, [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'message' => 'deleted'
        ]);

        $this->assertTrue(Company::find($company->id) == null);
        $this->assertTrue(UserCompany::where('company_id', $company->id)->count() == 0);
    }
}
