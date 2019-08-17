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
Route::prefix('auth')->group(function(){
    //  Handle auth
    Route::post('/register', 'Auth\RegisterController@register');
    Route::post('/login', 'Auth\LoginController@login');
    Route::post('/reset-password', 'Auth\LoginController@resetPassword');
    
    Route::post('/token', 'Auth\TokenController@token');

    //  Handle Invites
    //  ....

});

Route::middleware(['auth:api', 'api'])->group(function(){
    //  Invites
    Route::post('/invite', 'Auth\InviteController@invite');
    Route::delete('/invite/{id}', 'Auth\InviteController@deleteInvite');

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

Route::middleware('api')->prefix('open')->group(function(){
    Route::post('/campaigns/{campaign}/assign-phone', 'Open\CampaignController@assignPhone');
});

