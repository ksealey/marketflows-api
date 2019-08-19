<?php

namespace App\Listeners;

use App\Events\IncomingCallEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class IncomingCallListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  IncomingCallEvent  $event
     * @return void
     */
    public function handle(IncomingCallEvent $event)
    {
        //  Send data to logging service
        // ... 

        //  Fire webhooks
        // ...
        
    }
}
