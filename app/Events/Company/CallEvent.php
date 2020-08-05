<?php

namespace App\Events\Company;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $name;
    public $call;
    public $contact;
    public $company;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($name, $call, $contact = null, $company = null)
    {
        $this->name     = $name;
        $this->call     = $call;
        $this->contact  = $contact;
        $this->company  = $company;
    }
}
