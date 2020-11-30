<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*
|--------------------------------
| Handle user auth
|--------------------------------
*/
Route::prefix('/')->group(function(){
    Route::get('/', function(){
        return response([ 'status' => 'OK' ]);
    });

    Route::prefix('api')->group(function(){
        Route::get('/', function(){
            return response([ 
                'versions' => [ 
                    'v1' 
                ]
            ]);
        });

        Route::prefix('v1')->group(function(){

            Route::get('/', function(){
                return response([
                    'status' => 'OK'
                ]);
            });

            Route::middleware(['rate_limit:30,1'])->prefix('auth')->group(function(){
                Route::post('/request-email-verification', 'Auth\RegisterController@requestEmailVerification')
                    ->name('auth-request-email-verification');

                Route::post('/verify-email', 'Auth\RegisterController@verifyEmail')
                    ->name('auth-verify-email');

                Route::post('/register', 'Auth\RegisterController@register')
                    ->name('auth-register');

                Route::post('/login', 'Auth\LoginController@login')
                    ->name('auth-login');

                Route::post('/request-reset-password', 'Auth\LoginController@requestResetPassword')
                    ->name('auth-request-reset-password');

                Route::get('/reset-password', 'Auth\LoginController@checkResetPassword')
                    ->name('auth-check-reset-password');
                    
                Route::post('/reset-password', 'Auth\LoginController@resetPassword')
                    ->name('auth-handle-reset-password');  
            });

            /*
            |----------------------------------------
            | Handle authenticated user api calls
            |----------------------------------------
            */
            Route::middleware(['auth:api', 'api'])->group(function(){
                /*
                |--------------------------------
                | Handle development
                |--------------------------------
                */
                Route::prefix('development')->group(function(){
                    Route::post('suggest-feature', 'DevelopmentController@suggestFeature')
                         ->name('suggest-feature');
                         
                    Route::post('report-bug', 'DevelopmentController@reportBug')
                         ->name('report-bug');
                });

                /*
                |--------------------------------
                | Handle account
                |--------------------------------
                */
                Route::prefix('account')->group(function(){
                    Route::get('/', 'AccountController@read')
                        ->middleware('can:read,\App\Models\Account')
                        ->name('read-account');

                    Route::put('/', 'AccountController@update')
                        ->middleware('can:update,\App\Models\Account')
                        ->name('update-account');

                    Route::delete('/', 'AccountController@delete')
                        ->middleware('can:update,\App\Models\Account')
                        ->name('delete-account');
                });

                /*
                |--------------------------------
                | Handle summary
                |--------------------------------
                */
                Route::get('/summary', 'AccountController@summary')
                    ->middleware('can:summary,\App\Models\Account')
                    ->name('read-summary');

                /*
                |----------------------------------------
                | Handle current user
                |----------------------------------------
                */
                Route::prefix('me')->group(function(){
                    Route::get('/', 'ProfileController@me')
                        ->name('read-me');

                    Route::put('/', 'ProfileController@updateMe')
                        ->name('update-me');

                    Route::put('/email', 'ProfileController@updateEmail')
                        ->name('update-my-email');
                    /*
                    |----------------------------------------
                    | Handle alerts
                    |----------------------------------------
                    */
                    Route::prefix('alerts')->group(function(){
                        Route::get('/', 'AlertController@list')
                            ->name('list-alerts');

                        Route::delete('/{alert}', 'AlertController@delete')
                            ->middleware('can:delete,alert')
                            ->name('delete-alert'); 
                    });
                });

                /*
                |--------------------------------
                | Handle users
                |--------------------------------
                */
                Route::prefix('users')->group(function(){
                    Route::get('/', 'UserController@list')
                        ->middleware('can:list,App\Models\User')
                        ->name('list-users');

                    Route::get('/export', 'UserController@export')
                        ->middleware('can:list,App\Models\User')
                        ->name('export-users');

                    Route::post('/', 'UserController@create')
                        ->middleware('can:create,App\Models\User')
                        ->name('create-user');

                    Route::get('/{user}', 'UserController@read')
                        ->middleware('can:read,user')
                        ->name('read-user');

                    Route::put('/{user}', 'UserController@update')
                        ->middleware('can:update,user')
                        ->name('update-user');

                    Route::delete('/{user}', 'UserController@delete')
                        ->middleware('can:delete,user')
                        ->name('delete-user'); 
                });

                /*
                |------------------------------
                |  Handle API Credentials
                |------------------------------
                */
                Route::prefix('api-credentials')->group(function(){
                    Route::post('/', 'APICredentialController@create')
                        ->middleware('can:create,\App\Models\APICredential')
                        ->name('create-api-credential');

                    Route::get('/', 'APICredentialController@list')
                        ->middleware('can:list,\App\Models\APICredential')
                        ->name('list-api-credentials');

                    Route::delete('/{apiCredential}', 'APICredentialController@delete')
                        ->middleware('can:delete,apiCredential')
                        ->name('delete-api-credential');
                });

                /*
                |----------------------------------------
                | Handle payment methods
                |----------------------------------------
                */
                Route::prefix('payment-methods')->group(function(){
                    Route::get('/', 'PaymentMethodController@list')
                        ->middleware('can:list,\App\Models\PaymentMethod')
                        ->name('list-payment-methods'); 

                    Route::get('/export', 'PaymentMethodController@export')
                        ->middleware('can:list,\App\Models\PaymentMethod')
                        ->name('export-payment-methods'); 

                    Route::post('/create-intent', 'PaymentMethodController@createIntent')
                        ->middleware('can:create,\App\Models\PaymentMethod')
                        ->name('create-payment-intent');

                    Route::post('/', 'PaymentMethodController@create')
                        ->middleware('can:create,\App\Models\PaymentMethod')
                        ->name('create-payment-method');

                    Route::get('/{paymentMethod}', 'PaymentMethodController@read')
                        ->middleware('can:read,paymentMethod')
                        ->name('read-payment-method');

                    Route::put('/{paymentMethod}/make-primary', 'PaymentMethodController@makePrimary')
                        ->middleware('can:update,paymentMethod')
                        ->name('make-default-payment-method');

                    Route::put('/{paymentMethod}/authenticate', 'PaymentMethodController@authenticate')
                        ->middleware('can:update,paymentMethod')
                        ->name('authenticate-payment-method');

                    Route::delete('/{paymentMethod}', 'PaymentMethodController@delete')
                        ->middleware('can:delete,paymentMethod')
                        ->name('delete-payment-method'); 

                    Route::get('/{paymentMethod}/payments', 'PaymentMethodController@listPayments')
                        ->middleware('can:read,paymentMethod')
                        ->name('list-payment-method-payments'); 

                    Route::get('/{paymentMethod}/payments/export', 'PaymentMethodController@exportPayments')
                        ->middleware('can:read,paymentMethod')
                        ->name('export-payment-method-payments');
                });

                /*
                |----------------------------------------
                | Handle billing
                |----------------------------------------
                */
                Route::prefix('billing')->group(function(){
                    Route::get('/', 'BillingController@read')
                        ->middleware('can:read,\App\Models\Account')
                        ->name('read-billing'); 

                    Route::get('/current', 'BillingController@current')
                        ->middleware('can:read,\App\Models\Account')
                        ->name('current-billing'); 
                });

                /*
                |----------------------------------------
                | Handle billing statements
                |----------------------------------------
                */
                Route::prefix('billing-statements')->group(function(){
                    Route::get('/', 'BillingStatementController@list')
                        ->middleware('can:list,\App\Models\BillingStatement')
                        ->name('list-statements'); 

                    Route::get('/export', 'BillingStatementController@export')
                        ->middleware('can:list,\App\Models\BillingStatement')
                        ->name('export-statements'); 

                    Route::get('/current', 'BillingStatementController@current')
                        ->middleware('can:list,\App\Models\BillingStatement')
                        ->name('current-statement'); 

                    Route::get('/{billingStatement}', 'BillingStatementController@read')
                        ->middleware('can:read,billingStatement')
                        ->name('read-statement');

                    Route::post('/{billingStatement}/pay', 'BillingStatementController@pay')
                        ->middleware('can:update,billingStatement')
                        ->name('pay-statement');
                });

                /*
                |----------------------------------------
                | Handle support tickets
                |----------------------------------------
                */
                Route::prefix('support-tickets')->group(function(){
                    Route::get('/', 'SupportTicketController@list')
                        ->middleware('can:list,\App\Models\SupportTicket')
                        ->name('list-support-tickets'); 

                    Route::post('/', 'SupportTicketController@create')
                        ->middleware('can:create,\App\Models\SupportTicket')
                        ->name('create-support-ticket');

                    Route::get('/{supportTicket}', 'SupportTicketController@read')
                        ->middleware('can:read,supportTicket')
                        ->name('read-support-ticket');

                    Route::put('/{supportTicket}/close', 'SupportTicketController@close')
                        ->middleware('can:update,supportTicket')
                        ->name('close-support-ticket');

                    Route::post('/{supportTicket}/comments', 'SupportTicketController@createComment')
                        ->middleware('can:update,supportTicket')
                        ->name('create-support-ticket-comment');

                    Route::post('/{supportTicket}/attachments', 'SupportTicketController@createAttachment')
                        ->middleware('can:update,supportTicket')
                        ->name('create-support-ticket-attachment');

                    
                });

                /*
                |---------------------------------------
                | Handle company blocked phone numbers
                |---------------------------------------
                */
                Route::prefix('blocked-phone-numbers')->group(function(){
                    
                    Route::get('/', 'BlockedPhoneNumberController@list')
                        ->middleware('can:list,\App\Models\BlockedPhoneNumber')
                        ->name('list-blocked-phone-numbers');
                        
                    Route::get('/export', 'BlockedPhoneNumberController@export')
                        ->middleware('can:list,\App\Models\BlockedPhoneNumber')
                        ->name('export-blocked-phone-numbers');

                    Route::post('/', 'BlockedPhoneNumberController@create')
                        ->middleware('can:create,\App\Models\BlockedPhoneNumber')
                        ->name('create-blocked-phone-number');
                    
                    Route::prefix('{blockedPhoneNumber}')->group(function(){
                        Route::put('/', 'BlockedPhoneNumberController@update')
                            ->middleware('can:update,blockedPhoneNumber')
                            ->name('update-blocked-phone-number');

                        Route::get('/', 'BlockedPhoneNumberController@read')
                            ->middleware('can:read,blockedPhoneNumber')
                            ->name('read-blocked-phone-number');

                        Route::delete('/', 'BlockedPhoneNumberController@delete')
                            ->middleware('can:delete,blockedPhoneNumber')
                            ->name('delete-blocked-phone-number');
                    });
                });

                /*
                |---------------------------------------
                | Handle blocked calls
                |---------------------------------------
                */   
                Route::prefix('blocked-calls')->group(function(){
                    Route::get('/', 'BlockedCallController@list')
                        ->middleware('can:list,\App\Models\BlockedCall')
                        ->name('list-blocked-calls'); 

                    Route::get('/export', 'BlockedCallController@export')
                        ->middleware('can:list,\App\Models\BlockedCall')
                        ->name('export-blocked-calls'); 
                });


                /*
                |--------------------------------
                | Handle companies
                |--------------------------------
                */
                Route::prefix('companies')->group(function(){
                    Route::get('/', 'CompanyController@list')
                        ->middleware('can:list,\App\Models\Company')
                        ->name('list-companies');

                    Route::post('/', 'CompanyController@create')
                        ->middleware('can:create,\App\Models\Company')
                        ->name('create-company');

                    Route::get('/export', 'CompanyController@export')
                        ->middleware('can:list,\App\Models\Company')
                        ->name('export-companies');  

                    Route::get('/{company}', 'CompanyController@read')
                        ->middleware('can:read,company')
                        ->name('read-company');

                    Route::put('/{company}', 'CompanyController@update')
                        ->middleware('can:update,company')
                        ->name('update-company');

                    Route::delete('/{company}', 'CompanyController@delete')
                        ->middleware('can:delete,company')
                        ->name('delete-company'); 

                    /*
                    |-------------------------------------
                    | Company children endpoints
                    |-------------------------------------
                    */        
                    Route::prefix('/{company}')->group(function(){
                        /*
                        |--------------------------------
                        | Handle plugins
                        |--------------------------------
                        */
                        Route::prefix('plugins')->group(function(){
                            Route::get('/', 'Company\CompanyPluginController@list')
                                 ->middleware('can:list,company')
                                 ->name('list-plugins');

                            Route::post('/{pluginKey}', 'Company\CompanyPluginController@install')
                                 ->middleware('can:install,\App\Models\Company\CompanyPlugin,company')
                                 ->name('install-plugin');

                            Route::get('/{pluginKey}', 'Company\CompanyPluginController@read')
                                 ->middleware('can:read,\App\Models\Company\CompanyPlugin,company')
                                 ->name('read-plugin');

                            Route::put('/{pluginKey}', 'Company\CompanyPluginController@update')
                                 ->middleware('can:update,\App\Models\Company\CompanyPlugin,company')
                                 ->name('update-plugin');

                            Route::delete('/{pluginKey}', 'Company\CompanyPluginController@uninstall')
                                 ->middleware('can:uninstall,\App\Models\Company\CompanyPlugin,company')
                                 ->name('uninstall-plugin');
                        });

                        /*
                        |--------------------------------
                        | Handle audio clips
                        |--------------------------------
                        */
                        Route::prefix('audio-clips')->group(function(){
                            Route::get('/', 'Company\AudioClipController@list')
                                ->middleware('can:list,\App\Models\Company\AudioClip,company')
                                ->name('list-audio-clips');

                            Route::post('/', 'Company\AudioClipController@create')
                                ->middleware('can:create,App\Models\Company\AudioClip,company')
                                ->name('create-audio-clip');

                            Route::get('/{audioClip}', 'Company\AudioClipController@read')
                                ->middleware('can:read,audioClip,company')
                                ->name('read-audio-clip');

                            Route::put('/{audioClip}', 'Company\AudioClipController@update')
                                ->middleware('can:update,audioClip,company')
                                ->name('update-audio-clip');

                            Route::delete('/{audioClip}', 'Company\AudioClipController@delete')
                                ->middleware('can:delete,audioClip,company')
                                ->name('delete-audio-clip');
                        });  

                        /*
                        |--------------------------------
                        | Handle phone number configs
                        |--------------------------------
                        */
                        Route::prefix('phone-number-configs')->group(function(){
                            Route::get('/', 'Company\PhoneNumberConfigController@list')
                                ->middleware('can:list,\App\Models\Company\PhoneNumberConfig,company')
                                ->name('list-phone-number-configs');

                            Route::get('/export', 'Company\PhoneNumberConfigController@export')
                                ->middleware('can:list,\App\Models\Company\PhoneNumberConfig,company')
                                ->name('export-phone-number-configs');

                            Route::post('/', 'Company\PhoneNumberConfigController@create')
                                ->middleware('can:create,App\Models\Company\PhoneNumberConfig,company')
                                ->name('create-phone-number-config');

                            Route::get('/{phoneNumberConfig}', 'Company\PhoneNumberConfigController@read')
                                ->middleware('can:read,phoneNumberConfig,company')
                                ->name('read-phone-number-config');

                            Route::put('/{phoneNumberConfig}', 'Company\PhoneNumberConfigController@update')
                                ->middleware('can:update,phoneNumberConfig,company')
                                ->name('update-phone-number-config');

                            Route::delete('/{phoneNumberConfig}', 'Company\PhoneNumberConfigController@delete')
                                ->middleware('can:delete,phoneNumberConfig,company')
                                ->name('delete-phone-number-config');
                        }); 

                        /*
                        |--------------------------------
                        | Handle phone numbers
                        |--------------------------------
                        */
                        Route::prefix('phone-numbers')->group(function(){
                            Route::get('/available', 'Company\PhoneNumberController@checkNumbersAvailable')
                                    ->middleware('can:list,\App\Models\Company\PhoneNumber,company')
                                    ->name('phone-numbers-available');

                            Route::get('/export', 'Company\PhoneNumberController@export')
                                    ->middleware('can:list,\App\Models\Company\PhoneNumber,company')
                                    ->name('export-phone-numbers');

                            Route::get('/', 'Company\PhoneNumberController@list')
                                ->middleware('can:list,\App\Models\Company\PhoneNumber,company')
                                ->name('list-phone-numbers');

                            Route::post('/', 'Company\PhoneNumberController@create')
                                ->middleware('can:create,\App\Models\Company\PhoneNumber,company')
                                ->name('create-phone-number');

                            Route::get('/{phoneNumber}', 'Company\PhoneNumberController@read')
                                ->middleware('can:read,phoneNumber,company')
                                ->name('read-phone-number');

                            Route::put('/{phoneNumber}', 'Company\PhoneNumberController@update')
                                ->middleware('can:update,phoneNumber,company')
                                ->name('update-phone-number');

                            Route::delete('/{phoneNumber}', 'Company\PhoneNumberController@delete')
                                ->middleware('can:delete,phoneNumber,company')
                                ->name('delete-phone-number');
                        }); 

                        /*
                        |--------------------------------
                        | Handle keyword tracking pools
                        |--------------------------------
                        */
                        Route::prefix('keyword-tracking-pool')->group(function(){
                            Route::post('/', 'Company\KeywordTrackingPoolController@create')
                                ->middleware('can:create,\App\Models\Company\KeywordTrackingPool,company')
                                ->name('create-keyword-tracking-pool');

                            Route::get('/', 'Company\KeywordTrackingPoolController@read')
                                ->middleware('can:read,\App\Models\Company\KeywordTrackingPool,company')
                                ->name('read-keyword-tracking-pool');

                            Route::put('/', 'Company\KeywordTrackingPoolController@update')
                                ->middleware('can:update,\App\Models\Company\KeywordTrackingPool,company')
                                ->name('update-keyword-tracking-pool');

                            Route::post('/add-numbers', 'Company\KeywordTrackingPoolController@addNumbers')
                                ->middleware('can:update,\App\Models\Company\KeywordTrackingPool,company')
                                ->name('add-keyword-tracking-pool-numbers');
                            
                            Route::delete('/detach-numbers/{phoneNumber}', 'Company\KeywordTrackingPoolController@detachNumber')
                                ->middleware('can:update,\App\Models\Company\KeywordTrackingPool,company')
                                ->name('detach-keyword-tracking-pool-numbers');

                            Route::delete('/', 'Company\KeywordTrackingPoolController@delete')
                                ->middleware('can:delete,\App\Models\Company\KeywordTrackingPool,company')
                                ->name('delete-keyword-tracking-pool');
                        }); 

                        /*
                        |--------------------------------
                        | Handle company reports
                        |--------------------------------
                        */
                        Route::prefix('reports')->group(function(){
                            //
                            //  Canned reports
                            //
                            Route::prefix('system')->group(function(){
                                Route::get('/total-calls', 'Company\ReportController@totalCalls')
                                    ->middleware('can:list,\App\Models\Company\Report,company')
                                    ->name('report-total-calls');

                                Route::get('/call-sources', 'Company\ReportController@callSources')
                                    ->middleware('can:list,\App\Models\Company\Report,company')
                                    ->name('report-call-sources');

                                Route::get('/first-time-callers', 'Company\ReportController@firstTimeCallers')
                                    ->middleware('can:list,\App\Models\Company\Report,company')
                                    ->name('report-first-time-callers');
                                
                            });

                            //  
                            //  Custom reports
                            //
                            Route::get('/', 'Company\ReportController@list')
                                ->middleware('can:list,\App\Models\Company\Report,company')
                                ->name('list-reports');
                
                            Route::post('/', 'Company\ReportController@create')
                                ->middleware('can:create,\App\Models\Company\Report,company')
                                ->name('create-report');
                            
                            Route::get('/export', 'Company\ReportController@export')
                                ->middleware('can:list,\App\Models\Company\Report,company')
                                ->name('export-reports');

                            Route::get('/{report}', 'Company\ReportController@read')
                                ->middleware('can:read,report,company')
                                ->name('read-report');

                            Route::put('/{report}', 'Company\ReportController@update')
                                ->middleware('can:update,report,company')
                                ->name('update-report');

                            Route::delete('/{report}', 'Company\ReportController@delete')
                                ->middleware('can:delete,report,company')
                                ->name('delete-report');
                        }); 

                        /*
                        |------------------------------------
                        | Handle scheduled exports
                        |------------------------------------
                        */
                        Route::prefix('scheduled-exports')->group(function(){
                            Route::get('/', 'Company\ScheduledExportController@list')
                                ->middleware('can:list,\App\Models\Company\ScheduledExport,company')
                                ->name('list-scheduled-exports');

                            Route::post('/', 'Company\ScheduledExportController@create')
                                ->middleware('can:create,\App\Models\Company\ScheduledExport,company')
                                ->name('create-scheduled-export');

                            Route::get('/export', 'Company\ScheduledExportController@export')
                                ->middleware('can:list,\App\Models\Company\ScheduledExport,company')
                                ->name('export-scheduled-export');

                            Route::get('/{scheduledExport}', 'Company\ScheduledExportController@read')
                                ->middleware('can:read,scheduledExport,company')
                                ->name('read-scheduled-export');

                            Route::put('/{scheduledExport}', 'Company\ScheduledExportController@update')
                                ->middleware('can:update,scheduledExport,company')
                                ->name('update-scheduled-export');

                            Route::delete('/{scheduledExport}', 'Company\ScheduledExportController@delete')
                                ->middleware('can:delete,scheduledExport,company')
                                ->name('delete-scheduled-export');

                        });

                        /*
                        --------------------------------
                        | Handle contacts
                        |--------------------------------
                        */
                        Route::prefix('contacts')->group(function(){
                            Route::get('/', 'Company\ContactController@list')
                                ->middleware('can:list,\App\Models\Company\Contact,company')
                                ->name('list-contacts');

                            Route::get('/export', 'Company\ContactController@export')
                                ->middleware('can:list,\App\Models\Company\Contact,company')
                                ->name('export-contacts');

                            Route::post('/', 'Company\ContactController@create')
                                ->middleware('can:create,\App\Models\Company\Contact,company')
                                ->name('create-contact');

                            Route::get('/{contact}', 'Company\ContactController@read')
                                ->middleware('can:update,contact,company')
                                ->name('read-contact');

                            Route::put('/{contact}', 'Company\ContactController@update')
                                ->middleware('can:update,contact,company')
                                ->name('update-contact');

                            Route::delete('/{contact}', 'Company\ContactController@delete')
                                ->middleware('can:update,contact,company')
                                ->name('delete-contact');
                        });


                        /*
                        |--------------------------------
                        | Handle calls
                        |--------------------------------
                        */
                        Route::prefix('calls')->group(function(){
                            Route::get('/', 'Company\CallController@list')
                                ->middleware('can:list,\App\Models\Company\Call,company')
                                ->name('list-calls');

                            Route::get('/export', 'Company\CallController@export')
                                ->middleware('can:list,\App\Models\Company\Call,company')
                                ->name('export-calls');

                            Route::get('/{call}', 'Company\CallController@read')
                                ->middleware('can:read,call,company')
                                ->name('read-call');
                                
                            Route::put('/{call}/convert', 'Company\CallController@convert')
                                ->middleware('can:update,call,company')
                                ->name('convert-call');

                            Route::get('/{call}/recording', 'Company\CallController@readRecording')
                                ->middleware('can:read,call,company')
                                ->name('read-call-recording');

                            

                            Route::delete('/{call}/recording', 'Company\CallController@deleteRecording')
                                ->middleware('can:delete,call,company')
                                ->name('delete-call-recording');
                        });
                    }); 
                });

                /*
                |----------------------------------------
                | Miscellaneous
                |----------------------------------------
                */
                Route::prefix('tts')->group(function(){
                    Route::post('/say', 'TextToSpeechController@say')
                        ->name('text-to-speech-say');
                });
            });


            Route::middleware('twilio.webhooks')->group(function(){
                /*
                |--------------------------------
                | Handle incoming actions
                |--------------------------------
                */

                /*
                |--------------------------------
                | Handle incoming calls
                |--------------------------------
                */
                Route::prefix('incoming-calls')->group(function(){
                    Route::post('/', 'IncomingCallController@handleIncomingCall')
                            ->name('incoming-call');

                    Route::post('/collect', 'IncomingCallController@handleCollect')
                            ->name('incoming-call-collect');

                    Route::get('/whisper', 'IncomingCallController@handleCallWhisper')
                            ->name('incoming-call-whisper');

                    Route::post('/completed', 'IncomingCallController@handleCompletedCall')
                            ->name('incoming-call-completed');

                    Route::post('/duration', 'IncomingCallController@handleCompletedCallDuration')
                            ->name('incoming-call-duration');

                    Route::post('/recording-available', 'IncomingCallController@handleRecordingAvailable')
                            ->name('incoming-call-recording-available');
                });

                /*
                |--------------------------------
                | Handle incoming sms and mms
                |--------------------------------
                */
                Route::post('incoming-sms', 'IncomingSMSController@handleSMS')
                    ->name('incoming-sms');

                Route::post('incoming-mms', 'IncomingSMSController@handleMMS')
                        ->name('incoming-mms');
            });
        });
    });
});

Route::fallback(function(){
    return response([
        'error' => 'Not found'
    ], 404);
});