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

Route::middelware(['rate_limit:30,1'])
     ->post('init', 'OnlineController@init')
     ->name('online-init');
