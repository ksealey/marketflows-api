<?php

namespace App\Listeners\Company;

use App\Events\Company\PhoneNumberEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\WebSockets\Traits\PushesSocketData;

class PhoneNumberListener implements ShouldQueue
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
     * @param  PhoneNumberEvent  $event
     * @return void
     */
    public function handle(PhoneNumberEvent $event)
    {
       
    }
}
