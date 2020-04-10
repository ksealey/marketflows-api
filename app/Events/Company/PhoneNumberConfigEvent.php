<?php

namespace App\Events\Company;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PhoneNumberConfigEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $phoneNumberConfigs;
    public $action;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($user, $phoneNumberConfigs, $action)
    {
        $this->user                 = $user;
        $this->phoneNumberConfigs   = $phoneNumberConfigs;
        $this->action               = $action;
    }
}
