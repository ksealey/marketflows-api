<?php

namespace App\Events\Company;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AudioClipEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $audioClips;
    public $action;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($user, $audioClips, $action)
    {
        $this->user         = $user;
        $this->audioClips   = $audioClips;
        $this->action       = $action;
    }
}
