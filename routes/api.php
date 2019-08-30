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
    Route::post('/register', 'Auth\RegisterController@register');

    Route::post('/login', 'Auth\LoginController@login');

    Route::post('/reset-password', 'Auth\LoginController@resetPassword');

    Route::post('/reset-password/{userId}/{key}', 'Auth\LoginController@handleResetPassword');  
});

Route::middleware(['throttle:60,1', 'auth:api', 'api'])->group(function(){
    /*
    |--------------------------------
    | Handle user invites
    |--------------------------------
    */
    Route::prefix('user-invites')->group(function(){
        Route::post('/', 'UserInviteController@create')
             ->middleware('can:create,\App\Models\UserInvite');

        Route::get('/{userInvite}', 'UserInviteController@read')
             ->middleware('can:read,userInvite');

        Route::delete('/{userInvite}', 'UserInviteController@delete')
            ->middleware('can:delete,userInvite'); 
    });

    /*
    |--------------------------------
    | Handle roles
    |--------------------------------
    */
    Route::prefix('roles')->group(function(){
        Route::post('/', 'RoleController@create')
             ->middleware('can:create,\App\Models\Role');

        Route::get('/{role}', 'RoleController@read')
             ->middleware('can:read,role');

        Route::put('/{role}', 'RoleController@update')
             ->middleware('can:update,role');

        Route::delete('/{role}', 'RoleController@delete')
            ->middleware('can:delete,role'); 
    });

    /*
    |--------------------------------
    | Handle users
    |--------------------------------
    */
    Route::prefix('users')->group(function(){
        Route::get('/{user}', 'UserController@read')
             ->middleware('can:read,user');

        Route::put('/{user}', 'UserController@update')
             ->middleware('can:update,user');

        Route::delete('/{user}', 'UserController@delete')
            ->middleware('can:delete,user'); 

        Route::put('/{user}/change-password', 'UserController@changePassword')
            ->middleware('can:update,user');
    });

    /*
    |----------------------------------------
    | Handle payment methods
    |----------------------------------------
    */
    Route::prefix('payment-methods')->group(function(){
        Route::get('/', 'PaymentMethodController@list')
            ->middleware('can:list,\App\Models\PaymentMethod'); 

        Route::post('/', 'PaymentMethodController@create')
             ->middleware('can:create,\App\Models\PaymentMethod');

        Route::get('/{paymentMethod}', 'PaymentMethodController@read')
             ->middleware('can:read,paymentMethod');

        Route::put('/{paymentMethod}/make-default', 'PaymentMethodController@makeDefault')
             ->middleware('can:update,paymentMethod');

        Route::delete('/{paymentMethod}', 'PaymentMethodController@delete')
            ->middleware('can:delete,paymentMethod'); 
    });

    /*
    |--------------------------------
    | Handle companies
    |--------------------------------
    */
    Route::prefix('companies')->group(function(){
        Route::get('/', 'CompanyController@list')
            ->middleware('can:list,\App\Models\Company');

        Route::post('/', 'CompanyController@create')
             ->middleware('can:create,\App\Models\Company');

        Route::get('/{company}', 'CompanyController@read')
             ->middleware('can:read,company');

        Route::put('/{company}', 'CompanyController@update')
             ->middleware('can:update,company');

        Route::delete('/{company}', 'CompanyController@delete')
            ->middleware('can:delete,company'); 

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
                    ->middleware('can:list,\App\Models\Company\AudioClip');

                Route::post('/', 'Company\AudioClipController@create')
                    ->middleware('can:create,App\Models\Company\AudioClip');

                Route::get('/{audioClip}', 'Company\AudioClipController@read')
                    ->middleware('can:read,audioClip');

                Route::put('/{audioClip}', 'Company\AudioClipController@update')
                    ->middleware('can:update,audioClip');

                Route::delete('/{audioClip}', 'Company\AudioClipController@delete')
                    ->middleware('can:delete,audioClip');
            });  

            /*
            |--------------------------------
            | Handle phone number pools
            |--------------------------------
            */
            Route::prefix('phone-number-pools')->group(function(){
                Route::get('/', 'Company\PhoneNumberPoolController@list')
                    ->middleware('can:list,\App\Models\Company\PhoneNumberPool');

                Route::post('/', 'Company\PhoneNumberPoolController@create')
                    ->middleware('can:create,App\Models\Company\PhoneNumberPool');

                Route::get('/{phoneNumberPool}', 'Company\PhoneNumberPoolController@read')
                    ->middleware('can:read,phoneNumberPool');

                Route::put('/{phoneNumberPool}', 'Company\PhoneNumberPoolController@update')
                    ->middleware('can:update,phoneNumberPool');

                Route::delete('/{phoneNumberPool}', 'Company\PhoneNumberPoolController@delete')
                    ->middleware('can:delete,phoneNumberPool');
            });  

            /*
            |--------------------------------
            | Handle phone numbers
            |--------------------------------
            */
            Route::prefix('phone-numbers')->group(function(){
                Route::get('/', 'Company\PhoneNumberController@list')
                    ->middleware('can:list,\App\Models\Company\PhoneNumber');

                Route::post('/', 'Company\PhoneNumberController@create')
                    ->middleware('can:create,App\Models\Company\PhoneNumber');

                Route::get('/{phoneNumber}', 'Company\PhoneNumberController@read')
                    ->middleware('can:read,phoneNumber');

                Route::put('/{phoneNumber}', 'Company\PhoneNumberController@update')
                    ->middleware('can:update,phoneNumber');

                Route::delete('/{phoneNumber}', 'Company\PhoneNumberController@delete')
                    ->middleware('can:delete,phoneNumber');
            }); 
            
            /*
            |--------------------------------
            | Handle campaigns
            |--------------------------------
            */
            Route::prefix('campaigns')->group(function(){
                Route::get('/', 'Company\CampaignController@list')
                    ->middleware('can:list,\App\Models\Company\Campaign');

                Route::post('/', 'Company\CampaignController@create')
                    ->middleware('can:create,App\Models\Company\Campaign');

                Route::get('/{campaign}', 'Company\CampaignController@read')
                    ->middleware('can:read,campaign');

                Route::put('/{campaign}', 'Company\CampaignController@update')
                    ->middleware('can:update,campaign');

                Route::delete('/{campaign}', 'Company\CampaignController@delete')
                    ->middleware('can:delete,campaign');

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
                    Route::post('/phone-numbers','Company\Campaign\PhoneNumberController@create')
                         ->middleware('can:create,App\Models\Company\Campaign');

                    Route::delete('/phone-numbers/{campaignPhoneNumber}','Company\Campaign\PhoneNumberController@delete')
                         ->middleware('can:delete,campaign');
                    /*
                    |--------------------------------------
                    | Handle campaign phone number groups
                    |--------------------------------------
                    */
                    Route::post('/phone-number-groups','Company\Campaign\PhoneNumberGroupController@create')
                         ->middleware('can:create,App\Models\Company\Campaign');

                    Route::delete('/phone-number-groups/{campaignPhoneNumberGroup}','Company\Campaign\PhoneNumberGroupController@delete')
                         ->middleware('can:delete,campaign');

                    /*
                    |--------------------------------------
                    | Handle campaign spend
                    |--------------------------------------
                    */
                    Route::post('/spends','Company\Campaign\SpendController@create')
                         ->middleware('can:create,App\Models\Company\Campaign');

                    Route::put('/spends','Company\Campaign\SpendController@update')
                         ->middleware('can:update,campaign');

                    Route::delete('/spends/{campaignDomain}','Company\Campaign\SpendController@delete')
                         ->middleware('can:delete,campaign');
                
                    /*
                    |--------------------------------------
                    | Handle campaign targets (WEB Only)
                    |--------------------------------------
                    */
                    Route::post('/targets','Company\Campaign\TargetController@create')
                         ->middleware('can:create,App\Models\Company\Campaign');

                    Route::delete('/targets/{campaignTarget}','Company\Campaign\TargetController@delete')
                         ->middleware('can:delete,campaign');
                
                    /*
                    |--------------------------------------
                    | Handle campaign domains (WEB Only)
                    |--------------------------------------
                    */
                    Route::post('/domains','Company\Campaign\DomainController@create')
                         ->middleware('can:create,App\Models\Company\Campaign');

                    Route::put('/domains','Company\Campaign\DomainController@update')
                         ->middleware('can:update,campaign');

                    Route::delete('/domains/{campaignDomain}','Company\Campaign\DomainController@delete')
                         ->middleware('can:delete,campaign');
                });
            }); 
        }); 
    });
});

Route::middleware('api')->group(function(){
    Route::prefix('incoming')->group(function(){
        Route::get('call', 'Incoming\CallController@handleCall')->name('incoming-call');
        Route::get('call-status-changed', 'Incoming\CallController@handleCallStatusChanged')->name('call-status-changed');
        Route::get('call-recorded', 'Incoming\CallController@handleRecordedCall')->name('call-status-changed');
        Route::get('sms', 'Incoming\CallController@handleSms')->name('incoming-sms');
        Route::get('mms', 'Incoming\CallController@handleMms')->name('incoming-mms');
        Route::get('whisper', 'Incoming\CallController@whisper')->name('whisper');
    });

    Route::prefix('public')->group(function(){
        Route::prefix('user-invites')->group(function(){
            Route::get('/{userInvite}/{key}', 'UserInviteController@publicRead');
            Route::put('/{userInvite}/{key}', 'UserInviteController@publicAccept');
        });
    });
});




