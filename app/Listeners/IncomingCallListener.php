<?php

namespace App\Listeners;

use App\Events\IncomingCallEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Traits\FiresWebhooks;
use App\Models\Company;

class IncomingCallListener
{
    use FiresWebhooks;

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
     * @param  IncomingCallEvent  $event
     * @return void
     */
    public function handle(IncomingCallEvent $event)
    {
        $call    = $event->call;
        
        $company = $call->phoneNumber->company;
    
        return $this->fireWebhook($company, 'calls.started', $call->id,function(Company $company) use($call){
            unset($call->phoneNumber->company);

            return $call->toArray();
        });
    }
}
