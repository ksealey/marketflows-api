<?php

namespace App\Listeners\Company;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\Company\Webhook;
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
        $this->analytics = App::make('Analytics');
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
         
        $call->session = null;

        if( $call->keyword_tracking_pool_id )
            $call->session = $call->session;

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
        if( $company->ga_id && $eventName === Webhook::ACTION_CALL_END ){
            $analytics = $this->analytics
                            ->setProtocolVersion('1')
                            ->setTrackingId($company->ga_id)
                            ->setUserId($contact->uuid) 
                            ->setEventCategory('call')
                            ->setEventAction('called')
                            ->setEventLabel($contact->e164PhoneFormat())
                            ->setEventValue(1)
                            ->setAnonymizeIp(1)
                            ->setGeographicalOverride($contact->country);
            if( $call->campaign ){
                $analytics->setCampaignName($call->campaign);
            }
            if( $call->source ){
                $analytics->setCampaignSource($call->source);
            }
            if( $call->medium ){
                $analytics->setCampaignMedium($call->medium);
            }
            if( $call->content ){
                $analytics->setCampaignContent($call->content);
            }
            
            $analytics->sendEvent();
        }
    }
}
