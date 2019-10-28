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

Route::get('/', function(){
    return view('site.index', [
        'pageKey' => 'home'
    ]);
})->name('home');

/**
 * Features
 * 
 */
Route::prefix('features')->group(function(){
    $pageKey = 'features';
    
    Route::get('/', function() use($pageKey){
        return view('site.features.index',[
            'pageKey' => $pageKey
        ]);
    })->name('features');

    Route::get('/call-tracking', function() use($pageKey){
        return view('site.features.call-tracking', [
            'pageKey' => $pageKey
        ]);
    })->name('features-call-tracking');

    Route::get('/analytics', function() use($pageKey){
        return view('site.features.analytics', [
            'pageKey' => $pageKey
        ]);
    })->name('features-analytics');

    Route::get('/reporting', function() use($pageKey){
        return view('site.features.reporting', [
            'pageKey' => $pageKey
        ]);
    })->name('features-reporting');
});

/**
 * Pricing
 * 
 */
Route::get('/pricing', function(){
    return view('site.pricing', [
        'pageKey' => 'pricing'
    ]);
})->name('pricing');

/**
 * Registration
 * 
 */
Route::get('/register', function(){
    return view('auth.register', [
        'pageKey' => 'register'
    ]);
})->name('register');

/**
 * Login
 * 
 */
Route::get('/login', function(){
    return view('auth.login', [
        'pageKey' => 'login'
    ]);
})->name('login');




