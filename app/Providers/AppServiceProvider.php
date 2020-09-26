<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Response;
use Twilio\Rest\Client as Twilio;
use App\Services\PhoneNumberService;
use App\Services\ReportService;
use AWS;
use App;

class AppServiceProvider extends ServiceProvider
{
    //protected $defer = true;
    public $bindings = [
        'Stripe'    => \Stripe\Stripe::class
    ];

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //  Register Twilio
        $this->app->bind(Twilio::class, function($app){
            $config = config('services.twilio');
            
            return new Twilio($config['sid'], $config['token']);
        });

        //  Register Number Manager
        $this->app->bind(PhoneNumberService::class, function($app){
            return new PhoneNumberService();
        });

        //  Register report service
        $this->app->bind(ReportService::class, function($app){
            return new ReportService();
        });

        //  Register AWS
        $this->app->bind('App\Helpers\TextToSpeech', function($app){
            return new \App\Helpers\TextToSpeech(AWS::createClient('polly'));
        });

        $this->app->bind('Analytics', function(){
            return new \TheIconic\Tracking\GoogleAnalytics\Analytics(true);
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
