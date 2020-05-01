<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Alert;

class MeTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test can fetch self
     * 
     * @group me
     */
    public function testCanFetchSelf()
    {
        $response = $this->json('GET', route('read-me'));

        $response->assertStatus(200);
        $response->assertJSON([
            'id'        => $this->user->id,
            'account_id'=> $this->user->account_id,
            'role'      => $this->user->role,
            'timezone'  => $this->user->timezone,
            'first_name'=> $this->user->first_name,
            'last_name' => $this->user->last_name,
            'email'     => $this->user->email,
            'phone'     => $this->user->phone
        ]);
    }

    /**
     * Test can update self
     * 
     * @group me
     */
    public function testCanUpdateSelf()
    {
        $user     = factory(User::class)->make();
        $response = $this->json('PUT', route('update-me'), $user->toArray());
        $response->assertStatus(200);
        $response->assertJSON([
            'id'        => $this->user->id,
            'account_id'=> $this->user->account_id,
            'role'      => $this->user->role,
            'timezone'  => $user->timezone,
            'first_name'=> $user->first_name,
            'last_name' => $user->last_name,
            'email'     => $user->email,
            'phone'     => $user->phone
        ]);
    }

    /**
     * Test can fetch alerts
     * 
     * @group me
     */
    public function testCanFetchAlerts()
    {
        $alertCount = mt_rand(1, 20);
        factory(Alert::class, $alertCount)->create([
            'user_id' => $this->user->id
        ]);
        $response = $this->json('GET', route('list-alerts'));
        $response->assertStatus(200);
        $response->assertJSONstructure([
            'results' => [
                [
                    'id',
                    'user_id',
                    'type',
                    'message'
                ]
            ]
        ]);

        $response->assertJSON([
            'result_count'  => $alertCount,
            'limit'         => 250,
            'page'          => 1,
            'next_page'     => null,
            'total_pages'   => 1
        ]);
    }

    /**
     * Test can delete alerts
     * 
     * @group me
     */
    public function testCanDeleteAlert()
    {
        $alerts = factory(Alert::class, 3)->create([
            'user_id' => $this->user->id
        ]);

        foreach( $alerts as $alert ){

            $response  = $this->json('DELETE', route('delete-alert', [
                'alert' => $alert->id
            ]));

            $response->assertStatus(200);

            $this->assertDatabaseMissing('alerts', [
                'id'         => $alert->id,
                'deleted_at' => null
            ]);
        }
    }

    /**
     * Test other user cannot see my alerts
     * 
     * @group me
     */
    public function testOtherUserCannotSeeMyAlerts()
    {
        $alerts = factory(Alert::class, 3)->create([
            'user_id' => $this->user->id
        ]);

        //  Make sure other user cannot see alerts
        $otherUser = factory(User::class)->create([
            'account_id'  => $this->account->id
        ]); 
        
        $response  = $this->json('GET', route('list-alerts'), [], [ 
            'Authorization' => 'Bearer ' . $otherUser->auth_token
        ]);
        $response->assertStatus(200);
        $response->assertJSON([
            'results' => [],
            'result_count'  => 0,
        ]);
    }

    /**
     * Test I cannot see hidden alerts
     * 
     * @group me
     */
    public function testICannotSeeHiddenAlerts()
    {
        $alerts = factory(Alert::class, 3)->create([
            'user_id' => $this->user->id
        ]);

        $alerts = factory(Alert::class, 10)->create([
            'user_id'      => $this->user->id,
            'hidden_after' => now()->subMinutes(5)
        ]);

        $response = $this->json('GET', route('list-alerts'));
        $response->assertStatus(200);
        $response->assertJSONstructure([
            'results' => [
                [
                    'id',
                    'user_id',
                    'type',
                    'message'
                ]
            ]
        ]);

        $response->assertJSON([
            'result_count'  => 3,
            'limit'         => 250,
            'page'          => 1,
            'next_page'     => null,
            'total_pages'   => 1
        ]);
    }
}
