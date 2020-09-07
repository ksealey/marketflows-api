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
Route::middleware(['rate_limit:30,1'])->prefix('auth')->group(function(){
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

    Route::post('/verify-email', 'Auth\RegisterController@verifyEmail')
         ->name('verify-email');

    Route::get('/email-availability', 'Auth\RegisterController@emailAvailability')
         ->name('email-availability');
});

/*
|----------------------------------------
| Handle authenticated user api calls
|----------------------------------------
*/
Route::middleware(['auth:api', 'api'])->group(function(){
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
    |----------------------------------------
    | Handle current user
    |----------------------------------------
    */
    Route::prefix('me')->group(function(){
        Route::get('/', 'ProfileController@me')
             ->name('read-me');

        Route::put('/', 'ProfileController@updateMe')
             ->name('update-me');

        Route::post('/resend-verification-email', 'ProfileController@resendVerificationEmail')
            ->name('resend-verification-email');

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

        Route::post('/', 'PaymentMethodController@create')
             ->middleware('can:create,\App\Models\PaymentMethod')
             ->name('create-payment-method');

        Route::get('/{paymentMethod}', 'PaymentMethodController@read')
             ->middleware('can:read,paymentMethod')
             ->name('read-payment-method');

        Route::put('/{paymentMethod}/make-primary', 'PaymentMethodController@makePrimary')
             ->middleware('can:update,paymentMethod')
             ->name('make-default-payment-method');

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
    | Handle account blocked phone numbers
    |---------------------------------------
    
    Route::prefix('blocked-phone-numbers')->group(function(){
        Route::get('/', 'BlockedPhoneNumberController@list')
            ->middleware('can:list,\App\Models\AccountBlockedPhoneNumber')
            ->name('list-account-blocked-phone-numbers'); 

        Route::post('/', 'BlockedPhoneNumberController@create')
             ->middleware('can:create,\App\Models\AccountBlockedPhoneNumber')
             ->name('create-account-blocked-phone-number');

        Route::get('/{blockedPhoneNumber}', 'BlockedPhoneNumberController@read')
             ->middleware('can:read,blockedPhoneNumber')
             ->name('read-account-blocked-phone-number');

        Route::put('/{blockedPhoneNumber}', 'BlockedPhoneNumberController@update')
             ->middleware('can:update,blockedPhoneNumber')
             ->name('update-account-blocked-phone-number');

        Route::delete('/{blockedPhoneNumber}', 'BlockedPhoneNumberController@delete')
            ->middleware('can:delete,blockedPhoneNumber')
            ->name('delete-account-blocked-phone-number'); 
    });
    */
    


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
            |---------------------------------------
            | Handle company blocked phone numbers
            |---------------------------------------
            */
            Route::prefix('blocked-phone-numbers')->group(function(){
                
                Route::get('/', 'Company\BlockedPhoneNumberController@list')
                    ->middleware('can:list,\App\Models\Company\BlockedPhoneNumber,company')
                    ->name('list-company-blocked-phone-numbers');
                    
                Route::get('/export', 'Company\BlockedPhoneNumberController@export')
                    ->middleware('can:list,\App\Models\Company\BlockedPhoneNumber,company')
                    ->name('export-company-blocked-phone-numbers');

                Route::post('/', 'Company\BlockedPhoneNumberController@create')
                    ->middleware('can:create,\App\Models\Company\BlockedPhoneNumber,company')
                    ->name('create-company-blocked-phone-number');
               
                Route::prefix('{blockedPhoneNumber}')->group(function(){
                    Route::put('/', 'Company\BlockedPhoneNumberController@update')
                        ->middleware('can:update,blockedPhoneNumber,company')
                        ->name('update-company-blocked-phone-number');

                    Route::get('/', 'Company\BlockedPhoneNumberController@read')
                        ->middleware('can:read,blockedPhoneNumber,company')
                        ->name('read-company-blocked-phone-number');

                    Route::delete('/', 'Company\BlockedPhoneNumberController@delete')
                        ->middleware('can:delete,blockedPhoneNumber,company')
                        ->name('delete-company-blocked-phone-number');

                    /*
                    |---------------------------------------
                    | Handle company blocked calls
                    |---------------------------------------
                    */   
                    Route::prefix('blocked-calls')->group(function(){
                        Route::get('/', 'Company\BlockedPhoneNumber\BlockedCallController@list')
                            ->middleware('can:list,\App\Models\Company\BlockedPhoneNumber\BlockedCall,company,blockedPhoneNumber')
                            ->name('list-company-blocked-calls'); 

                        Route::get('/export', 'Company\BlockedPhoneNumber\BlockedCallController@export')
                            ->middleware('can:list,\App\Models\Company\BlockedPhoneNumber\BlockedCall,company,blockedPhoneNumber')
                            ->name('export-company-blocked-calls'); 
                    });
                });
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

                Route::get('/{call}', 'Company\CallController@read')
                    ->middleware('can:read,call,company')
                    ->name('read-call');

                Route::get('/{call}/recording', 'Company\CallController@readRecording')
                    ->middleware('can:read,call,company')
                    ->name('read-call-recording');

                Route::delete('/{call}/recording', 'Company\CallController@deleteRecording')
                    ->middleware('can:delete,call,company')
                    ->name('delete-call-recording');
            });

            /*
            |--------------------------------
            | Handle webhooks
            |--------------------------------
            */
            Route::prefix('webhooks')->group(function(){
                Route::post('/', 'Company\WebhookController@create')
                    ->middleware('can:read,company')
                    ->name('create-webhook');

                Route::get('/{webhook}', 'Company\WebhookController@read')
                    ->middleware('can:read,webhook,company')
                    ->name('read-webhook');

                Route::put('/{webhook}', 'Company\WebhookController@update')
                    ->middleware('can:update,webhook,company')
                    ->name('update-webhook');

                Route::delete('/{webhook}', 'Company\WebhookController@delete')
                    ->middleware('can:delete,webhook,company')
                    ->name('delete-webhook');

                Route::get('/', 'Company\WebhookController@list')
                    ->middleware('can:read,company')
                    ->name('list-webhooks');
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
        Route::post('/', 'IncomingCallController@handleCall')
                ->name('incoming-call');

        Route::post('/status-changed', 'IncomingCallController@handleCallStatusChanged')
                ->name('incoming-call-status-changed');

        Route::post('/recording-available', 'IncomingCallController@handleRecordingAvailable')
                ->name('incoming-call-recording-available');

        Route::get('/whisper', 'IncomingCallController@handleCallWhisper')
                ->name('incoming-call-whisper');

        Route::get('/collect', 'IncomingCallController@handleCollect')
                ->name('incoming-call-collect');

    });

    /*
    |--------------------------------
    | Handle incoming sms
    |--------------------------------
    */
    Route::post('incoming-sms', 'IncomingSMSController@handleSms')
            ->name('incoming-sms');

    Route::post('incoming-sms', 'IncomingSMSController@handleMms')
            ->name('incoming-mms');
});