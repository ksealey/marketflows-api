<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Response;
use Twilio\Rest\Client as Twilio;
use AWS;

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
        //  Register AWS
        $this->app->bind('App\Helpers\PhoneNumberManager', function($app){
            return new \App\Helpers\PhoneNumberManager();
        });

        //  Register AWS
        $this->app->bind('App\Helpers\TextToSpeech', function($app){
            return new \App\Helpers\TextToSpeech(AWS::createClient('polly'));
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
