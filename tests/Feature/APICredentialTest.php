<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\APICredential;

class APICredentialTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test creating api credentials
     * 
     * @group api-credentials
     */
    public function testCreate()
    {
        $creds = factory(APICredential::class)->make();

        $response = $this->json('POST', route('create-api-credential'), [
            'name' => $creds->name
        ]);

        $response->assertJSON([
            'name' => $creds->name
        ]);

        $response->assertJSONStructure([
            'name',
            'key',
            'secret'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('api_credentials', [
            'user_id'     => $this->user->id,
            'key'        => $response['key']
        ]);
    }

    /**
     * Test deleting
     * 
     * @group api-credentials
     */
    public function testDelete()
    {
        $creds = factory(APICredential::class)->create([
            'user_id'    => $this->user->id,
        ]);

        $response = $this->json('DELETE', route('delete-api-credential', [
            'apiCredential' => $creds->id
        ]));

        $response->assertJSON([
            'message' => 'deleted'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('api_credentials', [
            'id' => $creds->id
        ]);
    }

    /**
     * Test listing
     * 
     * @group api-credentials-
     */
    public function testListAPICredentials()
    {
        factory(APICredential::class, 5)->create([
            'user_id'    => $this->user->id,
        ]);

        $response = $this->json('GET', route('list-api-credentials'));
        $response->assertStatus(200);
        $response->assertJSON([
            "result_count" => 5,
            "limit" => 250,
            "page" => 1,
            "total_pages" =>  1,
            "next_page" => null,
        ]);

        $response->assertJSONStructure([
            "results" => [
                [
                    'id',
                    'name',
                    'key'
                ]
            ]
        ]);
    }

    /**
     * Test using api credentials
     * 
     * @group api-credentials
     */
    public function testUsingAPICredentials()
    {
        $secret = str_random(30);
        $creds = factory(APICredential::class)->create([
            'user_id'    => $this->user->id,
            'secret'     => bcrypt($secret)  
        ]);

        $response = $this->noAuthJson('GET', route('read-me', [
            'apiCredential' => $creds->id
        ]), [],  [
            'Authorization' => 'Basic ' . base64_encode($creds->key . ':' . $secret)
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            "id" => $this->user->id,
            "account_id" => $this->user->account_id,
            "role"       => User::ROLE_ADMIN
        ]);
    }

    /**
     * Test using invalid api credentials fail
     * 
     * @group api-credentials
     */
    public function testUsingInvalidAPICredentialsFail()
    {
        $secret = str_random(30);
        $creds = factory(APICredential::class)->create([
            'user_id'    => $this->user->id,
            'secret'     => bcrypt($secret)  
        ]);

        $response = $this->noAuthJson('GET', route('read-me', [
            'apiCredential' => $creds->id
        ]), [],  [
            'Authorization' => 'Basic ' . base64_encode($creds->key . ':' . $secret . 'foo')
        ]);

        $response->assertStatus(401);
    }
}
