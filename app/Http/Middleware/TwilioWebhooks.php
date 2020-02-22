<?php

namespace App\Http\Middleware;

use Closure;

class TwilioWebhooks
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if( ! $request->has('AccountSid') || $request->AccountSid != config('services.twilio.sid') )
            return response('Unauthorized', 403);

        return $next($request);
    }
}
