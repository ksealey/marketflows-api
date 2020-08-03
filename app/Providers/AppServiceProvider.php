<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Response;
use Twilio\Rest\Client as Twilio;
use \Stripe\Stripe;
use AWS;
use App;

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
        //  Register Twilio
        $this->app->bind('Twilio', function($app){
            $config = config('services.twilio');
            
            if( App::environment(['prod', 'production']) ){
                $client = new Twilio($config['sid'], $config['token']);
            }else{
                $client = new Twilio($config['test_sid'], $config['test_token']);
            }

            return $client;
        });

        //  Register Number Manager
        $this->app->bind('App\Helpers\PhoneNumberManager', function($app){
            $config = config('services.twilio');
            if( App::environment(['prod', 'production']) ){
                $client = new Twilio($config['sid'], $config['token']);
            }else{
                $client = new Twilio($config['test_sid'], $config['test_token']);
            }
            return new \App\Helpers\PhoneNumberManager($client);
        });

        //  Register AWS
        $this->app->bind('App\Helpers\TextToSpeech', function($app){
            return new \App\Helpers\TextToSpeech(AWS::createClient('polly'));
        });

        $this->app->bind('App\Helpers\PaymentManager', function(){
            return new \App\Helpers\PaymentManager();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        
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
