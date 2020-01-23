<?php

namespace Tests\Feature\Events;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use App\Models\Events\Session;
use App\Models\Events\SessionEvent;

class SessionEventTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     *  Test starting a session where a number should be assigned
     * 
     * @group feature-events-session-events
     */
    public function testPostPageViewEvent()
    {
        //
        //  Create Session
        //
        $user = $this->createUser();

        $session = factory(Session::class)->create([
            'company_id' => $this->company->id,
        ]);
        
        $event = factory(SessionEvent::class)->make([
            'company_id' => $this->company->id,
        ]);
        
        $response = $this->json('POST', route('events-create'), [
            'event_type' => 'PageView',
            'content'    => $event->content,
            'session_id'  => $session->id,
            'session_token'=> $session->token
        ], $this->authHeaders());

        $response->assertJSON([
            'message' => 'Accepted'
        ]);

        $response->assertStatus(202);

        $this->assertDatabaseHas('session_events', [
            'session_id'  => $session->id,
            'event_type'  => 'PageView',
            'content'     => $event->content
        ]);
    }
    
}
