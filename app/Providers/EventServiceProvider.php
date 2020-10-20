<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'App\Events\AlertEvent' => [
            'App\Listeners\AlertListener',
        ],
        'App\Events\Company\PhoneNumberEvent' => [
            'App\Listeners\Company\PhoneNumberListener',
        ],
        'App\Events\Company\CallEvent' => [
            'App\Listeners\Company\CallListener',
        ]
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
