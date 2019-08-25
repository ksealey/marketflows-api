<?php

namespace App\Listeners;

use App\Events\CompanyJsPublishedEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class CompanyJsPublishedListener
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
     * @param  CompanyJsPublished  $event
     * @return void
     */
    public function handle(CompanyJsPublishedEvent $event)
    {
        //
    }
}
