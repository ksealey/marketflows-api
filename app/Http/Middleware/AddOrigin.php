<?php

namespace App\Http\Middleware;

use Closure;

class AddOrigin
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
        $response = $next($request);
        $response->header('Access-Control-Allow-Origin', $request->header('Origin') ?: '*');
        $response->header('Access-Control-Allow-Credentials', 'true');
        return $response;
    }
}
