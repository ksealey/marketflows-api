<?php

namespace App\Events\Company;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PhoneNumberEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $phoneNumbers;
    public $action;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($user, $phoneNumbers, $action)
    {
        $this->user         = $user;
        $this->phoneNumbers = $phoneNumbers;
        $this->action       = $action;
    }
}
