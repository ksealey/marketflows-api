<?php
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Event Routes
|--------------------------------------------------------------------------
|
| Here you can register event routes. These have high rate limits
|
*/
Route::middleware(['throttle:5,1'])->group(function(){
    Route::post('/sessions', 'Events\SessionController@create'); 
});

Route::middleware(['throttle:360,1'])->group(function(){
    Route::post('/', 'Events\EventController@create');
});