<?php
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Publicly Exposed Routes
|--------------------------------------------------------------------------
|
| Here you can register event routes. These have high rate limits
|
*/
Route::prefix('sessions')->group(function(){
        Route::middleware(['throttle:60,1'])
                ->post('/', 'SessionController@create')
                ->name('exposed-session-create'); 

        Route::middleware(['throttle:240,1'])
                ->post('/events', 'SessionController@event')
                ->name('exposed-session-events');

        Route::middleware(['throttle:60,1'])
                ->post('/end', 'SessionController@end')
                ->name('exposed-session-end');   
});