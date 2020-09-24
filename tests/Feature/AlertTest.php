<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use \App\Models\Alert;

class AlertTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test listing alerts
     * 
     * @group alerts
     */
    public function testList()
    {
        factory(Alert::class, 10)->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->json('GET', route('list-alerts'));
        $response->assertJSON([
            "result_count"  => 10,
            "limit"         => 250,
            "page"          => 1,
            "total_pages"   => 1,
            "next_page"     => null,
            "results"       => [
                [
                    "user_id" => $this->user->id,
                    "kind"    => "Alert"
                ]
            ]
        ]);
        $response->assertStatus(200);
    }

    /**
     * Test listing alerts with all conditions
     * 
     * @group alerts
     */
    public function testListWithAllConditions()
    {
        factory(Alert::class, 10)->create([
            'user_id' => $this->user->id
        ]);

        $conditions = $this->createConditions(Alert::accessibleFields(), true);
        $response = $this->json('GET', route('list-alerts'), [
            'conditions' => $conditions,
        ]);

        $response->assertJSONStructure([
            "result_count",
            "limit",
            "page",
            "total_pages",
            "next_page",
            "results" => []
        ]);
        $response->assertStatus(200);
    }

    /**
     * Test listing alerts with condition
     * 
     * @group alerts
     */
    public function testListWithCondition()
    {
        $alerts = factory(Alert::class, 10)->create([
            'user_id' => $this->user->id
        ]);
        $alert = $alerts->first();

        $conditions = $this->createConditions(Alert::accessibleFields(), true);
        $response = $this->json('GET', route('list-alerts'), [
            'conditions' => json_encode([
                [
                    [
                        'field'    => 'alerts.title',
                        'operator' => 'EQUALS',
                        'inputs'   => [
                            $alert->title
                        ]
                    ]
                ]
            ]),
        ]);

        $response->assertJSON([
            "result_count"  => 1,
            "limit"         => 250,
            "page"          => 1,
            "total_pages"   => 1,
            "next_page"     => null,
            "results" => [
                [
                    'id' => $alert->id
                ]
            ]
        ]);
        $response->assertStatus(200);
    }
}
