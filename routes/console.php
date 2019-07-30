<?php

use Illuminate\Foundation\Inspiring;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');

Artisan::command('clear:password-resets', function () {
    \App\Models\Auth\PasswordReset::where('expires_at', '<', date('Y-m-d H:i:s'))->delete();
})->describe('Clear expired password resets');

Artisan::command('clear:invites', function () {
    \App\Models\Auth\UserInvite::where('expires_at', '<', date('Y-m-d H:i:s'))->delete();
})->describe('Clear expired invites');