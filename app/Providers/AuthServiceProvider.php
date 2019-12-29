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
    public function boot()
    {
        $this->registerPolicies();

        Gate::guessPolicyNamesUsing(function ($modelClass) {
            $modelClass = trim($modelClass, '\\');
            $modelClass = str_replace('\\Models\\', '\\Policies\\', $modelClass);
            
            return $modelClass . 'Policy';
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

                if( strtoupper($tokenType) === 'BASIC' )
                    return $this->applicationAuth($credentials, $request); 
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

        return $user && ! $user->disabled_until ? $user : null;
    }

    /**
     * Handle application auth
     *
     */
    public function applicationAuth($credentials, &$request)
    {
        $credentials = explode(':', base64_decode($credentials));
        if( count($credentials) !== 2 )
            return null;

        list($key, $secret) = $credentials;

        $app = Application::where('key', $key)->first();

        if( ! $app || ! $app->activated_at || $app->disabled_at )
            return null;

        if( ! password_verify($secret, $app->secret) )
            return null;

        $request->merge([
            'application_id' => $app->id
        ]);

        $user = User::find($app->user_id);

        return $user && ! $user->disabled_until ? $user : null;
    }
}
