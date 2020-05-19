<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use Faker\Generator as Faker;
use Tests\Models\OnlineUser;

$factory->define(OnlineUser::class, function (Faker $faker) {
    $paidReferrer = trim($faker->url, '/') . '?utm_medium=CpC';
    return [
        'tracking_entity_uuid'   => $faker->uuid,
        'http_referrer'  => $faker->url,
        'paid_url'       => $paidReferrer,
        'entry_url'      => $faker->url,
        'device_width'   => mt_rand(320, 1100),
        'device_height'  => mt_rand(320, 1100),
        'user_agent'     => $faker->userAgent
    ];
});