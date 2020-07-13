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
        'App\Events\AccountEvent' => [
            'App\Listeners\AccountListener',
        ],
        'App\Events\PaymentMethodEvent' => [
            'App\Listeners\PaymentMethodListener',
        ],
        'App\Events\AlertEvent' => [
            'App\Listeners\AlertListener',
        ],
        'App\Events\CompanyEvent' => [
            'App\Listeners\CompanyListener',
        ],
        'App\Events\Company\PhoneNumberEvent' => [
            'App\Listeners\Company\PhoneNumberListener',
        ],
        'App\Events\Company\PhoneNumberConfigEvent' => [
            'App\Listeners\Company\PhoneNumberConfigListener',
        ],
        'App\Events\Company\AudioClipEvent' => [
            'App\Listeners\Company\AudioClipListener',
        ],
        'App\Events\Company\BlockedPhoneNumberEvent' => [
            'App\Listeners\Company\BlockedPhoneNumberListener',
        ],
        'App\Events\Company\ReportEvent' => [
            'App\Listeners\Company\ReportListener',
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
