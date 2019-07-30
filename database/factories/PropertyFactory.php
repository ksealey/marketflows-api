<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Property::class, function (Faker $faker) {
    return [
        'name'   => $faker->company,
        'domain' => $faker->domainName,
        'key'    => $faker->ein,
    ];
});
