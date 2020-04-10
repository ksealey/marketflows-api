<?php

namespace App\Listeners\Company;

use App\Events\Company\BlockedPhoneNumberEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\WebSockets\Traits\PushesSocketData;
use Cache;

class BlockedPhoneNumberListener
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
     * @param  BlockedPhoneNumberEvent  $event
     * @return void
     */
    public function handle(BlockedPhoneNumberEvent $event)
    {
        //
    }
}
