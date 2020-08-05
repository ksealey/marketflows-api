<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Company\Webhook;
use Validator;

class WebhookController extends Controller
{
    static public $fields = [
        'webhooks.action',
        'webhooks.method',
        'webhooks.url'
    ];

    public function list(Request $request, Company $company)
    {
        $query = Webhook::where('webhooks.company_id', $company->id);

        return parent::results(
            $request,
            $query,
            [],
            self::$fields,
            'webhooks.created_at'
        );
    }

    public function create(Request $request, Company $company)
    {
        $rules = [
            'action'    => 'required|in:' . implode(',', Webhook::actions()),
            'method'    => 'required|in:' . implode(',', ['GET', 'POST']),
            'url'       => 'required|url|max:255',
            'params'    => 'json|max:512'
        ]; 

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() )
            return response([
                'error' => $validator->errors()->first()
            ], 400);

        $webhook = Webhook::create([
            'company_id'    => $company->id,
            'action'        => $request->action,
            'method'        => $request->method,
            'url'           => $request->url,
            'params'        => $request->params,
            'enabled_at'    => now()
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
            'params'    => 'json|max:512',
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
        if( $request->filled('params') )
            $webhook->params = $request->params;
        if( $request->filled('enabled') )
            $webhook->enabled_at = $request->enabled ? now() : null;

        $webhook->save();

        return response($webhook);
    }


    public function delete(Request $request, Company $company, Webhook $webhook)
    {
        $webhook->delete();

        return response([
            'message' => 'Deleted'
        ]);
    }
}
