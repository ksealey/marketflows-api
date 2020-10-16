<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class APITest extends TestCase
{
    /**
     * Test api root routes
     * 
     * @group api
     */
    public function testRootRoutes()
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        $response = $this->get('/api');
        $response->assertStatus(200);

        $response = $this->get('/api/v1');
        $response->assertStatus(200);
    }
}
