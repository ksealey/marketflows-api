<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use \App\Models\User;
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

        Auth::viaRequest('auth_token', function ($request){
            //  Check headers for bearer token
            $auth = $request->header('Authorization');
            if( ! $auth )
                return null;
            
            $segments = explode(' ', $auth);
            if( count($segments) !== 2 )
                return null;

            list($tokenType, $authToken) = $segments; 
            if( strtoupper($tokenType) !== 'BEARER' )
                return null;
        
            return User::where('auth_token', $authToken)->first();
        });
    }
}
