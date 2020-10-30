<?php

namespace App\Plugins;

use Illuminate\Http\Request;
use App\Contracts\PluginContract;
use App\Models\Company\CompanyPlugin;
use App\Services\WebhookService;
use App;

class WebhooksPlugin implements PluginContract
{
    public function onRules(Request $request)
    {
        return [

        ];
    }

    public function onHook(object $hook, CompanyPlugin $companyPlugin)
    {
        $webhookService = App::make(WebhookService::class);
        foreach( $companyPlugin->settings->webhooks as $event => $webhook ){
            if( $event !== $hook->event ) continue;
        
            $webhookService->sendWebhook(
                $webhook->method, 
                $webhook->url, 
                $hook->data->call->toArray()
            );  
        }
    }
}
