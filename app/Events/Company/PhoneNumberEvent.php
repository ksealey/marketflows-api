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

    public $account;
    public $phoneNumbers;
    public $action;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($account, $phoneNumbers, $action)
    {
        $this->account      = $account;
        $this->phoneNumbers = $phoneNumbers;
        $this->action       = $action;
    }
}
