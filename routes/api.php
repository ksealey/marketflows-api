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
    Route::post('/audio-clips', 'AudioClipController@create');
    Route::get('/audio-clips/{audioClip}', 'AudioClipController@read');
    Route::put('/audio-clips/{audioClip}', 'AudioClipController@update');
    Route::delete('/audio-clips/{audioClip}', 'AudioClipController@delete');
    Route::get('/audio-clips', 'AudioClipController@list');

    //  Phone Number Pools
    Route::post('/phone-number-pools', 'PhoneNumberPoolController@create');
    Route::get('/phone-number-pools/{phoneNumberPool}', 'PhoneNumberPoolController@read');
    Route::put('/phone-number-pools/{phoneNumberPool}', 'PhoneNumberPoolController@update');
    Route::delete('/phone-number-pools/{phoneNumberPool}', 'PhoneNumberPoolController@delete');
    Route::get('/phone-number-pools', 'PhoneNumberPoolController@list');

    //  Phone Numbers
    Route::post('/phone-numbers', 'PhoneNumberController@create');
    Route::get('/phone-numbers/{phoneNumber}', 'PhoneNumberController@read');
    Route::put('/phone-numbers/{phoneNumber}', 'PhoneNumberController@update');
    Route::delete('/phone-numbers/{phoneNumber}', 'PhoneNumberController@delete');
    Route::get('/phone-numbers', 'PhoneNumberController@list');

    //  Properties
    Route::post('/properties', 'PropertyController@create');
    Route::get('/properties/{property}', 'PropertyController@read');
    Route::put('/properties/{property}', 'PropertyController@update');
    Route::delete('/properties/{property}', 'PropertyController@delete');
    Route::get('/properties', 'PropertyController@list');

    //  Payment Methods
    Route::prefix('payment-methods')->group(function(){
        Route::post('/', 'PaymentMethodController@create');
        Route::get('/{paymentMethod}', 'PaymentMethodController@read');
        Route::put('/{paymentMethod}', 'PaymentMethodController@update');
        Route::delete('/{paymentMethod}', 'PaymentMethodController@delete');
        Route::get('/', 'PaymentMethodController@list');
    });

     //  Campaigns
     Route::post('/campaigns', 'CampaignController@create');
     Route::get('/campaigns/{campaign}', 'CampaignController@read');
     Route::put('/campaigns/{campaign}', 'CampaignController@update');
     Route::delete('/campaigns/{campaign}', 'CampaignController@delete');
     Route::get('/campaigns', 'CampaignController@list');
});

Route::prefix('react')->group(function(){
    Route::post('call', 'ReactController@handleCall');
    Route::post('sms', 'ReactController@handleSMS');
});
