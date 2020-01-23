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
Route::middleware(['throttle:30,1'])->prefix('sessions')->group(function(){
    Route::post('/start', 'Events\SessionController@start')
         ->name('events-session-start'); 

    Route::post('/end', 'Events\SessionController@end')
        ->name('events-session-end'); 
});

Route::middleware(['throttle:360,1'])->group(function(){
    Route::post('/', 'Events\SessionEventController@create')
        ->name('events-create');
});