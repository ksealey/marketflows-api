<?php

namespace App\Http\Middleware;

use Closure;

class SuspendedAccounts
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
        $user = $request->user();
        if( $user->login_disabled )
            return response([
                'error' => 'User disabled'
            ], 403);
        
        if($user->account->suspended_at){
            return response([
                'error' => 'Account suspended'
            ], 403);
        }

        return $next($request);
    }
}
