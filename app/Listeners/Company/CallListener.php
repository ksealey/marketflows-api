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
        $eventName = $event->name;
        $call      = $event->call;
        $contact   = $event->contact;
        $company   = $event->company;

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
                $params      = $webhook->params ? (array)$webhook->params : [];
                $params      = array_merge($call->toArray(), $params);
                $fieldsKey   = $webhook->method == 'GET' ? 'query' : 'form_params';
                $contentType = $webhook->method == 'GET' ? 'application/text' : 'application/x-www-form-urlencoded';  
                $request     = $client->request($webhook->method, $webhook->url, [
                    'headers' => [
                        'X-Sender'     => 'MarketFlows',
                        'Content-Type' => $contentType
                    ],
                    $fieldsKey => $params
                ]);
            }catch(\Exception $e){}
        }

        //
        //  Push ended calls to GA
        //
        //if( $company->ga_id && $eventName === Webhook::ACTION_CALL_END ){
            $phoneNumber = $call->phone_number;
            $endpoint = 'https://www.google-analytics.com/collect';
            $params = [
                'tid'           => $company->ga_id, // GA ID 
                'v'             => 1, // Version
                'aip'           => 1, // Anonymize IP
                't'             => 'event', // Type
                'cid'           => $contact->uuid, // Unique id
                'ec'            => 'call', // Event Category
                'ea'            => 'call',  // Event action
                'ds'            => 'MarketFlows', // Data Source
                'cn'            => $call->campaign, // Campaign
                'cs'            => $call->source, // Source
                'cm'            => $call->medium, // Medium
                'cc'            => $call->content, // Content
                'caller_number'     => $contact->country_code . $contact->phone,
                'caller_name'       => $contact->first_name . ' ' . $contact->last_name,
                'dialed_number'     => $phoneNumber->country_code . $phoneNumber->number,
                'dialed_number_name'=> $phoneNumber->name,
                'duration'          => $call->duration
            ];
            var_dump($params); exit;
        //}
    }
}
