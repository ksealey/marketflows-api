<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use \App\Models\User;
use \App\Models\APICredential;
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
                    return $this->userAuth($credentials);

                if( strtoupper($tokenType) === 'BASIC' )
                    return $this->apiCredentialAuth($credentials);
            }
            
            return null;
        });
    }

    /**
     * Handle user auth
     *
     */
    public function userAuth($token)
    {
        $user = $token ? User::where('auth_token', $token)->first() : null;

        return $user && ! $user->login_disabled && ! $user->login_disabled_at ? $user : null;
    }

    public function apiCredentialAuth($credentials)
    {
        $credentials = base64_decode($credentials);
        $pieces      = explode(':', $credentials);

        if( count($pieces) != 2 ) return null;

        list($key, $secret) = $pieces;

        $apiCredentials = APICredential::where('key', $key)->first();
        if( ! $apiCredentials ) 
            return null;

        if( ! password_verify($secret, $apiCredentials->secret) )
            return null;

        $user = User::find($apiCredentials->user_id);
        
        return $user && ! $user->login_disabled && ! $user->login_disabled_at ? $user : null;
    }
}
