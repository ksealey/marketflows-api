<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use \App\Models\User;
use \App\Models\Application;
use \Firebase\JWT\JWT;
use Exception;
use Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [

    ];

   
    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerPolicies(); 
    }


    public function boot()
    {
        Gate::guessPolicyNamesUsing(function ($modelClass) {
            $path = str_replace(
                    '\\Models\\', 
                    '\\Policies\\', 
                    trim($modelClass, '\\')
                ) . 'Policy';
            //var_dump($modelClass,$path); exit;
            return $path;
        });

        /**
         *  Allow a user to authenticate in with an auth token
         *
         */
        Auth::viaRequest('auth_token', function ($request){
            //  Check headers for bearer token
            $authToken = null;
            if( $auth = $request->header('Authorization') ){
                $segments = explode(' ', $auth);
                if( count($segments) !== 2 )
                    return null;

                list($tokenType, $credentials) = $segments; 
                if( strtoupper($tokenType) === 'BEARER' )
                    return $this->userAuth($credentials, $request->cookie('auth_token'));
            }
            
            return null;
        });
    }

    /**
     * Handle user auth
     *
     */
    public function userAuth($token, $fallbackToken = null)
    {
        $token = $token ?? $fallbackToken;

        $user = $token ? User::where('auth_token', $token)->first() : null;

        return $user && ! $user->login_disabled_until ? $user : null;
    }
}
