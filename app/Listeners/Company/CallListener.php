<?php

namespace App\Listeners\Company;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\Plugin;
use App;

class CallListener implements ShouldQueue
{
    protected $analytics;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        $call    = $event->call;
        $company = $call->company;
        $contact = $call->contact;
        if( $call->keyword_tracking_pool_id )
            $call->session = $call->session;

        $hook = (object)[
            'event' => $event->name,
            'data'  => (object)[
                'call'    => $call,
                'company' => $company,
                'contact' => $contact
            ]
        ];

        foreach( $company->plugins as $companyPlugin ){
            if( ! $companyPlugin->enabled_at ) continue;

            $plugin = Plugin::generate($companyPlugin->plugin_key);
            $plugin->onHook($hook, $companyPlugin);
        }
    }
}
