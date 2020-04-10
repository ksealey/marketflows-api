<?php

namespace App\Events\Company;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PhoneNumberPoolEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $phoneNumberPools;
    public $action;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($user, $phoneNumberPools, $action)
    {
        $this->user             = $user;
        $this->phoneNumberPools = $phoneNumberPools;
        $this->action           = $action;
    }
}
