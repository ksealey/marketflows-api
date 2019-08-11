<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
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
        //\App\Models\PaymentMethod::class => \App\Policies\PaymentMethodPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Auth::viaRequest('jwt', function ($request){
            //  Check headers for JWT
            $auth = $request->header('Authorization');
            if( ! $auth )
                return null;
            
            $segments = explode(' ', $auth);
            if( count($segments) !== 2 )
                return null;

            list($tokenType, $token) = $segments; 
            if( strtoupper($tokenType) !== 'BEARER' )
                return null;
            
            try{
                $token = base64_decode($token);

                $jwt = JWT::decode($token, env('APP_KEY'), array('HS256'));
            }catch(Exception $e){ return null; }
            
            if( $jwt->typ !== 'bearer' ) // Don't let refresh tokens pass for bearer tokens
                return null;

            return User::find($jwt->sub);
        });
    }
}
