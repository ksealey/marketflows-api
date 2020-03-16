<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Response;
use Twilio\Rest\Client as Twilio;

class AppServiceProvider extends ServiceProvider
{
    //protected $defer = true;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //  Register twilio
        $this->app->bind('Twilio\Rest\Client', function($app){
            $config = config('services.twilio');

            return new Twilio($config['sid'], $config['token']);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [Twilio::class];
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
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
}
