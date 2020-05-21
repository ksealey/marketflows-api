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

Route::middleware(['rate_limit:30,1'])
     ->post('init', 'OnlineController@init')
     ->name('online-init');

Route::middleware(['rate_limit:60,1'])
     ->post('events', 'OnlineController@event')
     ->name('online-event');

Route::middleware(['rate_limit:30,1'])
     ->post('heartbeat', 'OnlineController@heartbeat')
     ->name('online-heartbeat');
