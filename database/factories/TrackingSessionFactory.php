<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;
use Illuminate\Support\Str;

$factory->define(App\Models\TrackingSession::class, function (Faker $faker) {
    return [
        'uuid'              => $faker->uuid,
        'ip'                => $faker->ipv4,
        'device_width'      => mt_rand(10, 400),
        'device_height'     => mt_rand(500, 1000),
        'device_type'       => 'Mobile',
        'device_brand'      => 'Apple',
        'device_os'         => 'iOS',
        'browser_type'      => 'Safari',
        'browser_version'   => '11.10',
        'host'              => $faker->domainName,
        'source'            => $faker->realText(40),
        'medium'            => $faker->realText(40),
        'content'           => $faker->realText(40),
        'campaign'          => $faker->realText(40),
        'keyword'           => $faker->realText(40),
        'token'             => str_random(40),    
        'last_heartbeat_at' => now()
    ];
});
