<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;
use Illuminate\Support\Str;

$factory->define(App\Models\Events\Session::class, function (Faker $faker) {
    return [
        'persisted_id'      => $faker->uuid,
        'first_session'     => true,
        'ip'                => $faker->ipv4,
        'device_width'      => mt_rand(10, 400),
        'device_height'     => mt_rand(500, 1000),
        'device_type'       => 'Mobile',
        'device_brand'      => 'Apple',
        'device_os'         => 'iOS',
        'browser_type'      => 'Safari',
        'browser_version'   => '11.10',
        'token'             => str_random(40),    
    ];
});
