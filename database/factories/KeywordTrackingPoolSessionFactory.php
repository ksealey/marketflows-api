<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\KeywordTrackingPoolSession::class, function (Faker $faker) {
    return [
        'guuid'         => $faker->uuid,
        'uuid'          => $faker->uuid,
        'device_width'  => mt_rand(320, 1200),
        'device_height' => mt_rand(320, 1200),
        'device_type'   => 'DESKTOP',
        'device_browser'=> 'CHROME',
        'device_platform' => 'Windows',
        'landing_url'   => $faker->url,
        'last_url'      => $faker->url,
        'http_referrer' => $faker->url,
        'token'         => bcrypt('token'),
        'active' => 1,
        'last_activity_at' => now(),
        'end_after' => now()->addDays(30)
    ];
});
