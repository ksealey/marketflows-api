<?php 
namespace App\Http\Middleware;

use \Illuminate\Routing\Middleware\ThrottleRequests;

class RateLimit extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        return sha1(
            implode('|', $request->route()->methods()) .
            '|' . $request->route()->domain().
            '|' . $request->route()->uri().
            '|' . $request->auth_token
        );
    }
}