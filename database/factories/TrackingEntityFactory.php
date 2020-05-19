<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;
use Illuminate\Support\Str;

$factory->define(App\Models\TrackingEntity::class, function (Faker $faker) {
    return [
        'uuid' => $faker->uuid
    ];
});
