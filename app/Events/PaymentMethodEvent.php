<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PaymentMethodEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $paymentMethods;
    public $action;


    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($user, $paymentMethods, $action)
    {
        $this->user = $user;
        $this->paymentMethods = $paymentMethods;
        $this->action = $action;
    }
}
