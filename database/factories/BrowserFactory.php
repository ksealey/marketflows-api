<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(Tests\Models\Browser::class, function (Faker $faker) {
    return [
        'device_width'  => mt_rand(320, 1200),
        'device_height' => mt_rand(320, 1200),
        'user_agent'    => $faker->userAgent,
        'landing_url'   => $faker->url,
        'http_referrer' => $faker->url
    ];
});
