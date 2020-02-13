<?php

namespace App\Listeners;

use App\Events\IncomingCallEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Company;

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
        $call    = $event->call;
        
        //
        //  Do stuff
        //  ...
        //
    }
}
