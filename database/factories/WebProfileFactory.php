<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\WebProfile::class, function (Faker $faker) {
    return [
        'uuid' => $faker->uuid()
    ];
});
