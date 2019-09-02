<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'App\Events\IncomingCallEvent' => [
            'App\Listeners\IncomingCallListener',
        ],
        'App\Events\IncomingCallUpdatedEvent' => [
            'App\Listeners\IncomingCallUpdatedListener',
        ],
        'App\Events\CompanyJsPublishedEvent' => [
            'App\Listeners\CompanyJsPublishedListener',
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
