<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Company\Webhook;
use App\Services\WebhookService;
use Validator;
use App;

class WebhookController extends Controller
{
    static public $fields = [
        'webhooks.action',
        'webhooks.method',
        'webhooks.url'
    ];

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    public function list(Request $request, Company $company)
    {
        $webhooks = Webhook::where('webhooks.company_id', $company->id)
                           ->get();

        $grouped = [];
        foreach( $webhooks as $webhook ){
            if( ! isset($grouped[$webhook->action]) )
                $grouped[$webhook->action] = [];

            $grouped[$webhook->action][] = $webhook;
        }

        return response([
            'result_count' => count($webhooks),
            'results'      => $grouped
        ]);
    }

    public function create(Request $request, Company $company)
    {
        $rules = [
            'action'    => 'required|in:' . implode(',', Webhook::actions()),
            'method'    => 'required|in:' . implode(',', ['GET', 'POST']),
            'url'       => 'required|url|max:255'
        ]; 

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        $count = Webhook::where('company_id', $company->id)
                        ->where('action', $request->action)
                        ->count();

        if( $count >= Webhook::ACTION_LIMIT ){
            return response([
                'error' => 'You have reached the limit (' . Webhook::ACTION_LIMIT . ') of webhooks that can be added for this action.',
            ], 400);
        }

        $result = $this->webhookService->sendWebhook($request->method, $request->url, [
            'message' => 'Hello from MarketFlows'
        ]);

        if( ! $result->ok ){
            return response([
                'error' => 'Webhook URL did not return a 200-399 status code. Status code: ' . $result->status_code . '.'
            ], 400);
        }

        $webhook = Webhook::create([
            'company_id'    => $company->id,
            'action'        => $request->action,
            'method'        => $request->method,
            'url'           => $request->url,
            'enabled_at'    => now(),
            'created_by'    => $request->user()->id
        ]);
       
        return response($webhook, 201);
    }


    public function read(Request $request, Company $company, Webhook $webhook)
    {
        return response($webhook);
    }

    public function update(Request $request, Company $company, Webhook $webhook)
    {
        $rules = [
            'action'    => 'required|in:' . implode(',', Webhook::actions()),
            'method'    => 'required|in:' . implode(',', ['GET', 'POST']),
            'url'       => 'required|url|max:255',
            'enabled'   => 'boolean'
        ]; 

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        if( $request->filled('action') )
            $webhook->action = $request->action;
        if( $request->filled('method') )
            $webhook->method = $request->method;
        if( $request->filled('url') )
            $webhook->url = $request->url;
        if( $request->filled('enabled') )
            $webhook->enabled_at = $request->enabled ? now() : null;

        $result = $this->webhookService->sendWebhook($webhook->method, $webhook->url, [
            'message' => 'Hello from MarketFlows'
        ]);

        if( ! $result->ok ){
            return response([
                'error' => 'Webhook URL did not return a 200-399 status code. Status code: ' . $result->status_code . '.'
            ], 400);
        }

        $webhook->updated_by = $request->user()->id;
        $webhook->save();

        return response($webhook);
    }

    public function delete(Request $request, Company $company, Webhook $webhook)
    {
        $webhook->deleted_by = $request->user()->id;
        $webhook->deleted_at = now();
        $webhook->save();

        return response([
            'message' => 'Deleted'
        ]);
    }
}
