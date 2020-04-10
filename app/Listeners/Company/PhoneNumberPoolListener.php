<?php

namespace App\Listeners\Company;

use App\Events\Company\PhoneNumberPoolEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\WebSockets\Traits\PushesSocketData;
use Cache;

class PhoneNumberPoolListener
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
     * @param  PhoneNumberPoolEvent  $event
     * @return void
     */
    public function handle(PhoneNumberPoolEvent $event)
    {
        //
        //  Move numbers to bank, release if needed
        //
        
    }
}
