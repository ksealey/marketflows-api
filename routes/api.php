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
Route::prefix('auth')->group(function(){
    Route::post('/register', 'Auth\RegisterController@register');
    Route::post('/login', 'Auth\LoginController@login');
    Route::post('/reset-password', 'Auth\LoginController@resetPassword');
    Route::post('/reset-password/{userId}/{key}', 'Auth\LoginController@handleResetPassword');
});

Route::middleware(['auth:api', 'api'])->group(function(){
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
   
    //  Audio Clips
    Route::prefix('audio-clips')->group(function(){
        Route::post('/', 'AudioClipController@create');
        Route::get('/{audioClip}', 'AudioClipController@read');
        Route::put('/{audioClip}', 'AudioClipController@update');
        Route::delete('/{audioClip}', 'AudioClipController@delete');
        Route::get('/', 'AudioClipController@list');
    });

    //  Phone Number Pools
    Route::prefix('phone-number-pools')->group(function(){
        Route::post('/', 'PhoneNumberPoolController@create');
        Route::get('/{phoneNumberPool}', 'PhoneNumberPoolController@read');
        Route::put('/{phoneNumberPool}', 'PhoneNumberPoolController@update');
        Route::delete('/{phoneNumberPool}', 'PhoneNumberPoolController@delete');
        Route::get('/', 'PhoneNumberPoolController@list');
    });

    //  Phone Numbers
    Route::prefix('phone-numbers')->group(function(){
        Route::post('/', 'PhoneNumberController@create');
        Route::get('/{phoneNumber}', 'PhoneNumberController@read');
        Route::put('/{phoneNumber}', 'PhoneNumberController@update');
        Route::delete('/{phoneNumber}', 'PhoneNumberController@delete');
        Route::get('/', 'PhoneNumberController@list');
    });
    
    //  Properties
    Route::prefix('properties')->group(function(){
        Route::post('/', 'PropertyController@create');
        Route::get('/{property}', 'PropertyController@read');
        Route::put('/{property}', 'PropertyController@update');
        Route::delete('/{property}', 'PropertyController@delete');
        Route::get('/', 'PropertyController@list');
    });

    //  Payment Methods
    Route::prefix('payment-methods')->group(function(){
        Route::post('/', 'PaymentMethodController@create');
        Route::get('/{paymentMethod}', 'PaymentMethodController@read');
        Route::put('/{paymentMethod}', 'PaymentMethodController@update');
        Route::delete('/{paymentMethod}', 'PaymentMethodController@delete');
        Route::get('/', 'PaymentMethodController@list');
    });

     //  Campaigns
     Route::prefix('campaigns')->group(function(){
        Route::post('/', 'CampaignController@create');
        Route::get('/{campaign}', 'CampaignController@read');
        Route::put('/{campaign}', 'CampaignController@update');
        Route::delete('/{campaign}', 'CampaignController@delete');
        Route::get('/', 'CampaignController@list');
     });
     
});

Route::middleware('api')->group(function(){
    Route::prefix('incoming')->group(function(){
        Route::any('call', 'Incoming\CallController@handleCall')->name('incoming-call');
        Route::any('sms', 'Incoming\CallController@handleSms')->name('incoming-sms');
        Route::any('mms', 'Incoming\CallController@handleMms')->name('incoming-mms');
        Route::any('recorded-call', 'Incoming\CallController@handleRecordedCall')->name('recorded-call');
        Route::any('call-status-changed', 'Incoming\CallController@handleCallStatusChanged');
        Route::any('whisper', 'Incoming\CallController@whisper')->name('whisper');
    });

    Route::prefix('public')->group(function(){
        Route::prefix('user-invites')->group(function(){
            Route::get('/{userInvite}/{key}', 'UserInviteController@publicRead');
            Route::put('/{userInvite}/{key}', 'UserInviteController@publicAccept');
        });
    });
});


//  Public
Route::post('/sessions', 'SessionController@create')->name('session');
Route::post('/events', 'EventController@create')->name('event');


