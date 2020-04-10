<?php

namespace App\Listeners\Company;

use App\Events\Company\AudioClipEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\WebSockets\Traits\PushesSocketData;
use Cache;

class AudioClipListener
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
     * @param  AudioClipEvent  $event
     * @return void
     */
    public function handle(AudioClipEvent $event)
    {
        //  Delete remote files
        //  
        //  
    }
}
