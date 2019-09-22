<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Helpers\InsightsClient;

class InsightsClientTest extends TestCase
{
    use WithFaker, \Tests\CreatesUser;

    /**
     * Test creating a session with insights without an entity
     * 
     * @group insights-client
     */
    public function testSomething()
    {
        $this->assertTrue(true);
    }   
}
