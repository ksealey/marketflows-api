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

Route::prefix('web')->group(function(){
     Route::get('/', function(){
          return response([ 
              'versions' => [ 
                  'v1' 
              ]
          ]);
     });

     Route::prefix('v1')->group(function(){
          Route::get('/', function(){
              return response([
                  'status' => 'OK'
              ]);
          });

          Route::any('/start-session', 'WebSessionController@startSession')
               ->name('web-start-session');

          Route::post('/collect', 'WebSessionController@collect')
               ->name('web-collect');

          Route::post('/keep-alive', 'WebSessionController@keepAlive')
               ->name('web-keep-alive');

          Route::post('/number-status', 'WebSessionController@numberStatus')
               ->name('web-number-status');

          Route::post('/pause-session', 'WebSessionController@pauseSession')
               ->name('web-pause-session');
     });
});