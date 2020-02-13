<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\Role;
use \App\Models\User;

class RoleTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test creating a record
     *
     * @group feature-roles
     */
    public function testCreate()
    {
        $user = $this->createUser();
        $role = factory(Role::class)->make();
        $response = $this->json('POST', route('create-role'), [
            'name'   => $role->name,
            'policy' => $role->policy
        ], $this->authHeaders());
        $response->assertStatus(201);

        $response->assertJsonStructure([
            'role' => [
                'id',
                'name'
            ]
        ]);
    }

    /**
     * Test creating a record with an invalid policy
     *
     * @group feature-roles
     */
    public function testCreateWithInvalidPolicy()
    {
        $user = $this->createUser();
        $role = factory(Role::class)->make();
        $response = $this->json('POST', route('create-role'), [
            'name'   => $role->name,
            'policy' => json_encode([
                'policy' => [
                    [
                        'module'        => 'reports',
                        'permissions'   => '-'
                    ],
                    [
                        'module'        => 'companies',
                        'permissions'   => 'read'
                    ],
                    [
                        'module'        => 'payment-methods',
                        'permissions'   => 'create,read,update'
                    ],
                ]
            ])
        ], $this->authHeaders());

        $response->assertStatus(400);

        $response->assertJson([
            'error' => 'Policy invalid'
        ]);

        //  Try with the module missing
        $response = $this->json('POST', route('create-role'), [
            'name'   => $role->name,
            'policy' => json_encode([
                'policy' => [
                    [
                        'permissions'   => '*'
                    ]
                ]
            ])
        ], $this->authHeaders());

        $response->assertStatus(400);

        $response->assertJson([
            'error' => 'Policy invalid'
        ]);

        //  Try with permissions missing
        $response = $this->json('POST', route('create-role'), [
            'name'   => $role->name,
            'policy' => json_encode([
                'policy' => [
                    [
                        'module'        => '*'
                    ]
                ]
            ])
        ], $this->authHeaders());
        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'Policy invalid'
        ]);

         //  Try with invalid permissions
         $response = $this->json('POST', route('create-role'), [
            'name'   => $role->name,
            'policy' => json_encode([
                'policy' => [
                    [
                        'module'        => '*',
                        'permissions'   => 'somethinginvalid'
                    ]
                ]
            ])
        ], $this->authHeaders());
        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'Policy invalid'
        ]);

         //  Try with an empty array
         $response = $this->json('POST', route('create-role'), [
            'name'   => $role->name,
            'policy' => json_encode([
                'policy' => [
                    
                ]
            ])
        ], $this->authHeaders());

        $response->assertStatus(400);

        $response->assertJson([
            'error' => 'Policy invalid'
        ]);
    }

    /**
     * Test viewing a record
     *
     * @group feature-roles
     */
    public function testRead()
    {
        $user = $this->createUser();
        $role = factory(Role::class)->create([
            'account_id' =>  $user->account_id
        ]);

        $response = $this->json('GET', route('read-role', [
            'role' => $role->id
        ]), [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'role' => [
                'id',
                'name'
            ]
        ]);
    }


    /**
     * Test updating a record
     *
     * @group feature-roles
     */
    public function testUpdate()
    {
        $user = $this->createUser();

        $role = factory(Role::class)->create([
            'account_id' => $user->account_id,
            'user_id' => $user->id
        ]);

        $roleUpdate = factory(Role::class)->make([
            'policy' => json_encode([
                'policy' => [
                    [
                        'module' => '*',
                        'permissions' => 'update'
                    ]
                ]
            ])
        ]);

        $response = $this->json('PUT', route('update-role', [
            'role' => $role->id
        ]), [
            'name'   => $roleUpdate->name,
            'policy' => $roleUpdate->policy
        ], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJson([
            'role' => [
                'id'     => $role->id,
                'name'   => $roleUpdate->name,
                'policy' => $roleUpdate->policy
            ]
        ]);
    }

    /**
     * Test updating a record with an invalid policy
     *
     * @group feature-roles
     */
    public function testUpdateWithInvalidPolicy()
    {
        $user = $this->createUser();
        $role = factory(Role::class)->create([
            'account_id' => $user->account_id,
            'user_id' => $user->id
        ]);

        $response = $this->json('PUT', route('update-role', [
            'role' => $role->id
        ]), [
            'name'   => $role->name,
            'policy' => json_encode([
                'policy' => [
                    [
                        'module'        => 'reports',
                        'permissions'   => '-'
                    ],
                    [
                        'module'        => 'companies',
                        'permissions'   => 'read'
                    ],
                    [
                        'module'        => 'payment-methods',
                        'permissions'   => 'create,read,update'
                    ],
                ]
            ])
        ], $this->authHeaders());

        $response->assertStatus(400);

        $response->assertJson([
            'error' => 'Policy invalid'
        ]);

        //  Try with the module missing
        $response = $this->json('PUT', route('update-role', [
            'role' => $role->id
        ]), [
            'name'   => $role->name,
            'policy' => json_encode([
                'policy' => [
                    [
                        'permissions'   => '*'
                    ]
                ]
            ])
        ], $this->authHeaders());

        $response->assertStatus(400);

        $response->assertJson([
            'error' => 'Policy invalid'
        ]);

        //  Try with permissions missing
        $response = $this->json('PUT', route('update-role', [
            'role' => $role->id
        ]), [
            'name'   => $role->name,
            'policy' => json_encode([
                'policy' => [
                    [
                        'module'        => '*'
                    ]
                ]
            ])
        ], $this->authHeaders());
        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'Policy invalid'
        ]);

        //  Try with invalid permissions
        $response = $this->json('PUT', route('update-role', [
            'role' => $role->id
        ]), [
            'name'   => $role->name,
            'policy' => json_encode([
                'policy' => [
                    [
                        'module'        => '*',
                        'permissions'   => 'somethinginvalid'
                    ]
                ]
            ])
        ], $this->authHeaders());
        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'Policy invalid'
        ]);

        //  Try with an empty array
        $response = $this->json('PUT', route('update-role', [
            'role' => $role->id
        ]), [
            'name'   => $role->name,
            'policy' => json_encode([
                'policy' => [
                    
                ]
            ])
        ], $this->authHeaders());

        $response->assertStatus(400);

        $response->assertJson([
            'error' => 'Policy invalid'
        ]);
    }

    /**
     * Test deleting a record
     *
     * @group feature-roles
     */
    public function testDelete()
    {
        $user = $this->createUser();

        $role = factory(Role::class)->create([
            'account_id' => $user->account_id,
            'user_id' => $user->id
        ]);

        $response = $this->json('DELETE', route('delete-role', [
            'role' => $role->id
        ]), [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'deleted'
        ]);

        $this->assertTrue(Role::find($role->id) == null);
    }
}
