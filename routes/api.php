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
Route::middleware(['throttle:30,1'])->prefix('auth')->group(function(){
    Route::post('/register', 'Auth\RegisterController@register')
         ->name('auth-register');

    Route::post('/login', 'Auth\LoginController@login')
         ->name('auth-login');

    Route::post('/reset-password', 'Auth\LoginController@resetPassword')
         ->name('auth-reset-password');

    Route::post('/reset-password/{userId}/{key}', 'Auth\LoginController@handleResetPassword')
         ->name('auth-handle-reset-password');  
});

/*
|----------------------------------------
| Handle authenticated user api calls
|----------------------------------------
*/
Route::middleware(['throttle:60,1', 'auth:api', 'api'])->group(function(){
    Route::get('/me', function(Request $request){
        return response([
            'user' => $request->user()
        ]);
    })->name('me');

    /*
    |--------------------------------
    | Handle roles
    |--------------------------------
    */
    Route::prefix('roles')->group(function(){
        Route::post('/', 'RoleController@create')
             ->middleware('can:create,\App\Models\Role')
             ->name('create-role');

        Route::get('/{role}', 'RoleController@read')
             ->middleware('can:read,role')
             ->name('read-role');

        Route::put('/{role}', 'RoleController@update')
             ->middleware('can:update,role')
             ->name('update-role');

        Route::delete('/{role}', 'RoleController@delete')
            ->middleware('can:delete,role')
            ->name('delete-role'); 
    });

    /*
    |--------------------------------
    | Handle users
    |--------------------------------
    */
    Route::prefix('users')->group(function(){
        //
        // TODO: Add route to create a user
        //
        Route::get('/{user}', 'UserController@read')
             ->middleware('can:read,user')
             ->name('read-user');

        Route::put('/{user}', 'UserController@update')
             ->middleware('can:update,user')
             ->name('update-user');

        Route::delete('/{user}', 'UserController@delete')
            ->middleware('can:delete,user')
            ->name('delete-user'); 

        Route::put('/{user}/change-password', 'UserController@changePassword')
            ->middleware('can:update,user')
            ->name('change-user-password');
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

        Route::post('/', 'PaymentMethodController@create')
             ->middleware('can:create,\App\Models\PaymentMethod')
             ->name('create-payment-method');

        Route::get('/{paymentMethod}', 'PaymentMethodController@read')
             ->middleware('can:read,paymentMethod')
             ->name('read-payment-method');

        Route::put('/{paymentMethod}/make-default', 'PaymentMethodController@makeDefault')
             ->middleware('can:update,paymentMethod')
             ->name('update-payment-method');

        Route::delete('/{paymentMethod}', 'PaymentMethodController@delete')
            ->middleware('can:delete,paymentMethod')
            ->name('delete-payment-method'); 
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
            | Handle web source fields
            |--------------------------------
            */
            Route::prefix('web-source-fields')->group(function(){
                Route::get('/', 'Company\WebSourceFieldController@list')
                    ->middleware('can:list,\App\Models\Company\WebSourceField')
                    ->name('list-web-source-fields');

                Route::post('/', 'Company\WebSourceFieldController@create')
                    ->middleware('can:create,\App\Models\Company\WebSourceField')
                    ->name('create-web-source-field');

                Route::get('/{webSourceField}', 'Company\WebSourceFieldController@read')
                    ->middleware('can:read,webSourceField')
                    ->name('read-web-source-field');

                Route::put('/{webSourceField}', 'Company\WebSourceFieldController@update')
                    ->middleware('can:update,webSourceField')
                    ->name('update-web-source-field');

                Route::delete('/{webSourceField}', 'Company\WebSourceFieldController@delete')
                    ->middleware('can:delete,webSourceField')
                    ->name('delete-web-source-field');
            });

            /*
            |--------------------------------
            | Handle audio clips
            |--------------------------------
            */
            Route::prefix('audio-clips')->group(function(){
                Route::get('/', 'Company\AudioClipController@list')
                    ->middleware('can:list,\App\Models\Company\AudioClip')
                    ->name('list-audio-clips');

                Route::post('/', 'Company\AudioClipController@create')
                    ->middleware('can:create,App\Models\Company\AudioClip')
                    ->name('create-audio-clip');

                Route::get('/{audioClip}', 'Company\AudioClipController@read')
                    ->middleware('can:read,audioClip')
                    ->name('read-audio-clip');

                Route::put('/{audioClip}', 'Company\AudioClipController@update')
                    ->middleware('can:update,audioClip')
                    ->name('update-audio-clip');

                Route::delete('/{audioClip}', 'Company\AudioClipController@delete')
                    ->middleware('can:delete,audioClip')
                    ->name('delete-audio-clip');
            });  

            /*
            |--------------------------------
            | Handle phone number configs
            |--------------------------------
            */
            Route::prefix('phone-number-configs')->group(function(){
                Route::get('/', 'Company\PhoneNumberConfigController@list')
                    ->middleware('can:list,\App\Models\Company\PhoneNumberConfig')
                    ->name('list-phone-number-configs');

                Route::post('/', 'Company\PhoneNumberConfigController@create')
                    ->middleware('can:create,App\Models\Company\PhoneNumberConfig')
                    ->name('create-phone-number-config');

                Route::get('/{phoneNumberConfig}', 'Company\PhoneNumberConfigController@read')
                    ->middleware('can:read,phoneNumberConfig')
                    ->name('read-phone-number-config');

                Route::put('/{phoneNumberConfig}', 'Company\PhoneNumberConfigController@update')
                    ->middleware('can:update,phoneNumberConfig')
                    ->name('update-phone-number-config');

                Route::delete('/{phoneNumberConfig}', 'Company\PhoneNumberConfigController@delete')
                    ->middleware('can:delete,phoneNumberConfig')
                    ->name('delete-phone-number-config');
            }); 

            /*
            |--------------------------------
            | Handle phone number pools
            |--------------------------------
            */
            Route::prefix('phone-number-pools')->group(function(){
                Route::get('/', 'Company\PhoneNumberPoolController@list')
                    ->middleware('can:list,\App\Models\Company\PhoneNumberPool')
                    ->name('list-phone-number-pools');

                Route::post('/', 'Company\PhoneNumberPoolController@create')
                    ->middleware('can:create,App\Models\Company\PhoneNumberPool')
                    ->name('create-phone-number-pool');

                Route::get('/{phoneNumberPool}', 'Company\PhoneNumberPoolController@read')
                    ->middleware('can:read,phoneNumberPool')
                    ->name('read-phone-number-pool');

                Route::put('/{phoneNumberPool}', 'Company\PhoneNumberPoolController@update')
                    ->middleware('can:update,phoneNumberPool')
                    ->name('update-phone-number-pool');

                Route::delete('/{phoneNumberPool}', 'Company\PhoneNumberPoolController@delete')
                    ->middleware('can:delete,phoneNumberPool')
                    ->name('delete-phone-number-pool');
            });  

            /*
            |--------------------------------
            | Handle phone numbers
            |--------------------------------
            */
            Route::prefix('phone-numbers')->group(function(){
                Route::get('/', 'Company\PhoneNumberController@list')
                    ->middleware('can:list,\App\Models\Company\PhoneNumber')
                    ->name('list-phone-numbers');

                Route::post('/', 'Company\PhoneNumberController@create')
                    ->middleware('can:create,App\Models\Company\PhoneNumber')
                    ->name('create-phone-number');

                Route::get('/{phoneNumber}', 'Company\PhoneNumberController@read')
                    ->middleware('can:read,phoneNumber')
                    ->name('read-phone-number');

                Route::put('/{phoneNumber}', 'Company\PhoneNumberController@update')
                    ->middleware('can:update,phoneNumber')
                    ->name('update-phone-number');

                Route::delete('/{phoneNumber}', 'Company\PhoneNumberController@delete')
                    ->middleware('can:delete,phoneNumber')
                    ->name('delete-phone-number');

                /*
                |--------------------------------
                | Handle calls
                |--------------------------------
                */
                Route::prefix('/{phoneNumber}')->group(function(){
                    Route::get('/calls', 'Company\PhoneNumber\CallController@list')
                        ->middleware('can:read,phoneNumber')
                        ->name('list-calls');

                    Route::get('/calls/{call}', 'Company\PhoneNumber\CallController@read')
                        ->middleware('can:read,phoneNumber')
                        ->name('read-call');
                });
            }); 
            
            /*
            |--------------------------------
            | Handle campaigns
            |--------------------------------
            */
            Route::prefix('campaigns')->group(function(){
                Route::get('/', 'Company\CampaignController@list')
                    ->middleware('can:list,\App\Models\Company\Campaign')
                    ->name('list-campaigns');

                Route::post('/', 'Company\CampaignController@create')
                    ->middleware('can:create,App\Models\Company\Campaign')
                    ->name('create-campaign');

                Route::get('/{campaign}', 'Company\CampaignController@read')
                    ->middleware('can:read,campaign')
                    ->name('read-campaign');

                Route::put('/{campaign}', 'Company\CampaignController@update')
                    ->middleware('can:update,campaign')
                    ->name('update-campaign');

                Route::delete('/{campaign}', 'Company\CampaignController@delete')
                    ->middleware('can:delete,campaign')
                    ->name('delete-campaign');

                /*
                |--------------------------------
                | Campaign children endpoints
                |--------------------------------
                */
                Route::prefix('/{campaign}')->group(function(){
                    /*
                    |--------------------------------
                    | Handle campaign phone numbers
                    |--------------------------------
                    */
                    Route::post('/phone-numbers','Company\Campaign\PhoneNumberController@add')
                         ->middleware('can:update,campaign')
                         ->name('add-campaign-phone-number');

                    Route::delete('/phone-numbers','Company\Campaign\PhoneNumberController@remove')
                         ->middleware('can:update,campaign')
                         ->name('remove-campaign-phone-number');

                    /*
                    |--------------------------------------
                    | Handle campaign spends
                    |--------------------------------------
                    */
                    Route::post('/spends','Company\Campaign\SpendController@create')
                         ->middleware('can:update,campaign')
                         ->name('create-campaign-spend');

                    Route::put('/spends/{spend}','Company\Campaign\SpendController@update')
                         ->middleware('can:update,campaign')
                         ->name('update-campaign-spend');

                    Route::delete('/spends/{spend}','Company\Campaign\SpendController@delete')
                         ->middleware('can:update,campaign')
                         ->name('delete-campaign-spend');
                
                    /*
                    |--------------------------------------
                    | Handle campaign domains (WEB Only)
                    |--------------------------------------
                    */
                    Route::post('/domains','Company\Campaign\DomainController@create')
                         ->middleware('can:update,campaign')
                         ->name('create-campaign-domain');

                    Route::put('/domains/{domain}','Company\Campaign\DomainController@update')
                         ->middleware('can:update,campaign')
                         ->name('update-campaign-domain');

                    Route::delete('/domains/{domain}','Company\Campaign\DomainController@delete')
                         ->middleware('can:update,campaign')
                         ->name('delete-campaign-domain');

                    /*
                    |--------------------------------------
                    | Handle campaign targets (WEB Only)
                    |--------------------------------------
                    */
                    Route::post('/targets','Company\Campaign\TargetController@create')
                         ->middleware('can:update,campaign')
                         ->name('create-campaign-target');

                    Route::put('/targets/{target}','Company\Campaign\TargetController@update')
                         ->middleware('can:update,campaign')
                         ->name('update-campaign-target');

                    Route::delete('/targets/{target}','Company\Campaign\TargetController@delete')
                         ->middleware('can:update,campaign')
                         ->name('delete-campaign-target');
                });
            }); 
        }); 
    });
});


Route::middleware('api')->group(function(){
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
        Route::get('/', 'IncomingCallController@handleCall')
                ->name('incoming-call');

        Route::get('/status-changed', 'IncomingCallController@handleCallStatusChanged')
                ->name('incoming-call-status-changed');

        Route::post('/recording-available', 'IncomingCallController@handleRecordingAvailable')
                ->name('incoming-call-recording-available');

        Route::get('/whisper', 'IncomingCallController@handleCallWhisper')
                ->name('incoming-call-whisper');
    });

    /*
    |--------------------------------
    | Handle incoming sms
    |--------------------------------
    */
    Route::get('incoming-sms', 'IncomingSMSController@handleSms')
            ->name('incoming-sms');

    /*
    |--------------------------------
    | Handle incoming mms
    |--------------------------------
    */
    Route::get('incoming-mms', 'IncomingMMSController@handleMms')
            ->name('incoming-mms');

    /*
    |-----------------------------------------
    | Handle web sessions
    |------------------------------------------
    */
    Route::middleware('throttle:30,1')->prefix('web-sessions')->group(function(){
        Route::post('/', 'WebSessionController@create');
        Route::any('/{sessionUUID}/end', 'WebSessionController@end');
    });
});


