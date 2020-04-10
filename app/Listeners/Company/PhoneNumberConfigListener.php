<?php

namespace App\Listeners\Company;

use App\Events\Company\PhoneNumberConfigEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\WebSockets\Traits\PushesSocketData;
use Cache;

class PhoneNumberConfigListener
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
     * @param  PhoneNumberConfigEvent  $event
     * @return void
     */
    public function handle(PhoneNumberConfigEvent $event)
    {
        //
    }
}
