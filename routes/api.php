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

Route::middleware('auth:api')->group(function(){
    //  
    Route::post('/invite', 'Auth\InviteController@invite');
    Route::delete('/invite/{id}', 'Auth\InviteController@deleteInvite');

    //  Properties
    Route::post('/properties', 'PropertyController@create');
    Route::get('/properties/{id}', 'PropertyController@read');
    Route::put('/properties/{id}', 'PropertyController@update');
    Route::delete('/properties/{id}', 'PropertyController@delete');
    Route::get('/properties', 'PropertyController@list');
});
