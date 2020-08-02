<?php

namespace App\Listeners\Company;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\Company\Webhook;

class CallListener implements ShouldQueue
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
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        $call      = $event->call;
        $eventName = $event->name;

        //
        //  Fire webhooks
        //
        $webhooks = Webhook::where('company_id', $call->company_id)
                           ->where('action', $eventName)
                           ->whereNotNull('enabled_at')
                           ->get();
            
        $client = new \GuzzleHttp\Client();
        foreach( $webhooks as $webhook ){
            try{
                $params     = $webhook->params ? (array)$webhook->params : [];
                $params     = array_merge($call->toArray(), $params);
                $fieldsKey  = $webhook->method == 'GET' ? 'query' : 'form_params';
                $fieldsType = $webhook->method == 'GET' ? 'application/text' : 'application/x-www-form-urlencoded';  
                $request = $client->request($webhook->method, $webhook->url, [
                    'headers' => [
                        'X-Sender'     => 'MarketFlows',
                        'Content-Type' => $fieldsType
                    ],
                    $fieldsKey => $params
                ]);
            }catch(\Exception $e){}
        }
    }
}
