<?php

namespace App\Plugins;

use Illuminate\Http\Request;
use App\Contracts\PluginContract;
use App\Models\Company\CompanyPlugin;
use App\Models\Plugin as PluginModel;
use App;

class GoogleAnalyticsPlugin implements PluginContract
{
    public function onRules(Request $request)
    {

    }

    public function onHook(object $hook, CompanyPlugin $companyPlugin)
    {
        if( $hook->event !== PluginModel::EVENT_CALL_END ) return;

        $call    = $hook->data->call;
        $company = $hook->data->company;
        $contact = $hook->data->contact;

        $analytics = App::make('Analytics'); 
        $analytics->setProtocolVersion('1')
                ->setTrackingId($companyPlugin->settings->ga_id)
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
