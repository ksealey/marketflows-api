<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Response;
use Twilio\Rest\Client as Twilio;
use App\Services\PhoneNumberService;
use App\Services\ReportService;
use App\Services\TranscribeService;
use App\Helpers\TextToSpeech;
use \GuzzleHttp\Client as HTTPClient;
use Aws\TranscribeService\TranscribeServiceClient;
use AWS;
use App;
use URL;

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

        $this->app->bind('TestTwilio', function($app){
            $config = config('services.twilio');
            
            return new Twilio($config['test_sid'], $config['test_token']);
        });

        //  Register Number Manager
        $this->app->bind(PhoneNumberService::class, function($app){
            return new PhoneNumberService();
        });

        //  Register report service
        $this->app->bind(ReportService::class, function($app){
            return new ReportService();
        });

        //  Register AWS Text to Speach
        $this->app->bind(TextToSpeech::class, function($app){
            return new TextToSpeech(AWS::createClient('polly'));
        });

        $this->app->bind(TranscribeService::class, function($app){
            $config = config('services.transcribe');
            return new TranscribeService(
                new TranscribeServiceClient([
                    'region' => $config['region'],
                    'version' => 'latest',
                    'credentials' => [
                        'key'    => $config['key'],
                        'secret' => $config['secret']
                    ]
                ]),
                new HTTPClient()
            );
        });

        $this->app->bind('HTTPClient', function($app){
            return new HTTPClient();
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

        if(App::environment([ 'production', 'prod' ])) {
            URL::forceScheme('https');
        }
    }
}
