<?php

namespace App\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Traits\FiresWebhooks;
use App\Models\Company;

class IncomingCallUpdatedListener
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
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        $call    = $event->call;
        
        $company = $call->phoneNumber->company;
    
        $webhookActionId = $call->status == 'completed' ? 'calls.completed' : 'calls.updated';

        return $this->fireWebhook($company, $webhookActionId, $call->id, function(Company $company) use($call){
            unset($call->phoneNumber->company);

            return $call->toArray();
        });
    }
}
