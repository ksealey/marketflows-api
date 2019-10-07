<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\WebDevice::class, function (Faker $faker) {
    return [
        'uuid' => $faker->uuid(),
        'ip'   => '127.0.0.1',
        'width'=> mt_rand(100, 600),
        'height'=> mt_rand(700, 900)
    ];
});
