<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class APITest extends TestCase
{
    use WithFaker;

    /**
     * Test api root routes
     * 
     * @group api
     */
    public function testRootRoutes()
    {
        $faker = $this->faker();

        $domain   = $faker->domainName;

        // /
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', '*');

        $response = $this->withHeaders([
            'Origin' => $domain
        ])->get('/');
        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', $domain);

        //  /api
        $response = $this->withHeaders([
            'Origin' => ''
        ])->get('/api');
        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', '*');

        $response = $this->withHeaders([
            'Origin' => $domain
        ])->get('/api');
        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', $domain);

        //  /api/v1
        $response = $this->withHeaders([
            'Origin' => ''
        ])->get('/api/v1');
        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', '*');

        $response = $this->withHeaders([
            'Origin' => $domain
        ])->get('/api/v1');
        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', $domain);
    }

    /**
     * Test web api root routes
     * 
     * @group api
     */
    public function testRootWebRoutes()
    {
        $faker = $this->faker();

        $domain   = $faker->domainName;

        //  /api
        $response = $this->withHeaders([
            'Origin' => ''
        ])->get('/web');
        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', '*');

        $response = $this->withHeaders([
            'Origin' => $domain
        ])->get('/web');
        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', $domain);

        //  /api/v1
        $response = $this->withHeaders([
            'Origin' => ''
        ])->get('/web/v1');
        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', '*');

        $response = $this->withHeaders([
            'Origin' => $domain
        ])->get('/web/v1');
        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', $domain);
    }
}
