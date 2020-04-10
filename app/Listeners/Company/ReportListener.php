<?php

namespace App\Listeners\Company;

use App\Events\Company\ReportEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\WebSockets\Traits\PushesSocketData;
use Cache;

class ReportListener
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
     * @param  ReportEvent  $event
     * @return void
     */
    public function handle(ReportEvent $event)
    {
        //
    }
}
