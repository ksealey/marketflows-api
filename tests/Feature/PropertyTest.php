<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PropertyTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test creating a property
     * 
     * @group properties
     */
    public function testCreate()
    {
        $user = $this->createUser();

        $p = factory(\App\Models\Property::class)->make();

        $response = $this->json('POST', 'http://localhost/v1/properties', [
            'name'   => $p->name,
            'domain' => $p->domain
        ], $this->authHeaders());

        $response->assertJson([
            'message' => 'created',
            'ok'      => true
        ]);

        $response->assertJsonStructure([
            'message',
            'ok',
            'property'
        ]);

        $response->assertStatus(201);
    }

    /**
     * Test fetching a property
     * 
     * @group properties
     */
    public function testRead()
    {
        $user = $this->createUser();

        $p = factory(\App\Models\Property::class)->create([
            'created_by' => $user->id,
            'company_id' => $user->company_id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/properties/' . $p->id, [], $this->authHeaders());

        $response->assertJson([
            'message' => 'success',
            'ok'      => true,
        ]);

        $response->assertJsonStructure([
            'message',
            'ok',
            'property' => [
                'id',
                'name',
                'domain',
                'created_at',
                'updated_at'
            ]
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test updating a property
     * 
     * @group properties
     */
    public function testUpdate()
    {
        $user = $this->createUser();

        $p = factory(\App\Models\Property::class)->create([
            'created_by' => $user->id,
            'company_id' => $user->company_id
        ]);

        $np = factory(\App\Models\Property::class)->make();

        $response = $this->json('PUT', 'http://localhost/v1/properties/' . $p->id, [
            'name' => $np->name,
            'domain' => $np->domain
        ], $this->authHeaders());

        $response->assertJson([
            'message' => 'updated',
            'ok'      => true,
        ]);

        $response->assertJsonStructure([
            'message',
            'ok',
            'property' => [
                'id',
                'name',
                'domain',
                'created_at',
                'updated_at'
            ]
        ]);

        $response->assertStatus(200);

        $property = \App\Models\Property::find($p->id);
        $this->assertTrue($property->name == $np->name);
        $this->assertTrue($property->domain == $np->domain);
    }

    /**
     * Test deleting properties
     * 
     * @group properties
     */
    public function testDelete()
    {
        $user = $this->createUser();

        $p = factory(\App\Models\Property::class)->create([
            'created_by' => $user->id,
            'company_id' => $user->company_id
        ]);

        $response = $this->json('DELETE', 'http://localhost/v1/properties/' . $p->id, [], $this->authHeaders());

        $response->assertJson([
            'message' => 'deleted',
            'ok'      => true,
        ]);

        $response->assertStatus(200);

        $property = \App\Models\Property::find($p->id);
        $this->assertTrue($property == null);
    }

    /**
     * Test listing properties
     * 
     * @group properties
     */
    public function testList()
    {
        $user = $this->createUser();

        $p = factory(\App\Models\Property::class)->create([
            'created_by' => $user->id,
            'company_id' => $user->company_id
        ]);

        $p2 = factory(\App\Models\Property::class)->create([
            'created_by' => $user->id,
            'company_id' => $user->company_id
        ]);

        $response = $this->json('GET', 'http://localhost/v1/properties', [], $this->authHeaders());

        $response->assertJson([
            'message' => 'success',
            'ok'      => true,
        ]);

        $response->assertJsonStructure([
            'message',
            'ok',
            'properties' => [
                [
                    'id',
                    'name',
                    'domain',
                    'created_at',
                    'updated_at'
                ],
                [
                    'id',
                    'name',
                    'domain',
                    'created_at',
                    'updated_at'
                ]
            ]
        ]);

        $response->assertStatus(200);

        $this->assertTrue(\App\Models\Property::where('company_id', $user->id)->count() == 2);
    }
}
