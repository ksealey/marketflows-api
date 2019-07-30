<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('auth')->group(function(){
    Route::get('/login', 'Auth\LoginController@viewLogin');
    Route::get('/reset-password/{userId}/{key}', 'Auth\LoginController@viewResetPassword');
    Route::post('/reset-password/{userId}/{key}', 'Auth\LoginController@handleResetPassword');
});


