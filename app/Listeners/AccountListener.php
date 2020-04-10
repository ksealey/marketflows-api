<?php

namespace App\Listeners;

use App\Events\AccountEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\WebSockets\Traits\PushesSocketData;
use Cache;

class AccountListener
{
    use PushesSocketData;
    
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
     * @param  AccountEvent  $event
     * @return void
     */
    public function handle(AccountEvent $event)
    {
        //
    }
}
