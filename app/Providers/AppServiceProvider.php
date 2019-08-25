<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        Response::macro('xmlResponse', function($content, $responseCode = 200){
            return response($content, $responseCode)
                    ->header('Content-Type', 'application/xml');
        });

        Response::macro('jsResponse', function($content, $responseCode = 200){
            return response($content, $responseCode)
                    ->header('Content-Type', 'application/javascript');
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
