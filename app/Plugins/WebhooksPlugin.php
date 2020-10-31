<?php

namespace App\Plugins;

use Illuminate\Http\Request;
use App\Contracts\PluginContract;
use App\Models\Company\CompanyPlugin;
use App\Services\WebhookService;
use App\Models\Plugin;
use App;

class WebhooksPlugin implements PluginContract
{
    public function onDefaultSettings()
    {
        return (object)[
            'webhooks' => [
                (object)[
                    'event'  => Plugin::EVENT_CALL_START,
                    'method' => 'POST',
                    'url'    => ''
                ],
                (object)[
                    'event'  => Plugin::EVENT_CALL_END,
                    'method' => 'POST',
                    'url'    => ''
                ],
            ]
        ];
    }

    public function onValidateSettings(object $settings)
    {
        if( empty($settings->webhooks) || ! is_array($settings->webhooks) )
            return false;

        $webhookService = App::make(WebhookService::class);
        foreach( $settings->webhooks as $webhook ){
            if( empty($webhook->event) || ! in_array($webhook->event, [Plugin::EVENT_CALL_START, Plugin::EVENT_CALL_END] ))
                return false; 
           
            if( empty($webhook->method) || ! in_array($webhook->method, ['POST', 'GET']) )
                return false;

            if( ! empty($webhook->url) && (! is_string($webhook->url) || ! $webhookService->isValidWebhookURL($webhook->url) || strlen($webhook->url) > 500 ) )
                return false;
        }

        return true;
    }

    public function onHook(object $hook, CompanyPlugin $companyPlugin)
    {
        $webhookService = App::make(WebhookService::class);
        foreach( $companyPlugin->settings->webhooks as $webhook ){
            if( $webhook->event !== $hook->event || ! $webhook->url ) continue;
        
            $webhookService->sendWebhook(
                $webhook->method, 
                $webhook->url, 
                $hook->data->call->toArray()
            );  
        }
    }
}
