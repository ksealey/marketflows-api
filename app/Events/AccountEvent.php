<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AccountEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $account;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($user, $account, $action)
    {
        $this->user    = $user;
        $this->account = $account;
    }
}
