<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Faker\Factory as Faker;

class PropertyTest extends TestCase
{
    /**
     * Test parsing a domain
     *
     * @group properties
     * @return void
     */
    public function testParsingDomain()
    {
        $faker  = Faker::create();
        $url    = $faker->url;

        $this->assertTrue(stripos($url, '/') !== false);

        $domain = \App\Models\Property::domain($url);

        $this->assertTrue(stripos($domain, '/') === false);
    }
}
