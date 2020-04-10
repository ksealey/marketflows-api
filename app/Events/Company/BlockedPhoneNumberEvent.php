<?php

namespace App\Events\Company;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class BlockedPhoneNumberEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $blockedPhoneNumbers;
    public $action;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($user, $blockedPhoneNumbers, $action)
    {
        $this->user                 = $user;
        $this->blockedPhoneNumbers  = $blockedPhoneNumbers;
        $this->action               = $action;
    }
}
