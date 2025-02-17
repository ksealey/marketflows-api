<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use App\Models\User;
use App\Models\Alert;

class AlertEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $alerts;
    public $action;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(User $user, $alerts, $action)
    {
        $this->user   = $user; 
        $this->alerts = $alerts;
        $this->action = $action;
    }
}
