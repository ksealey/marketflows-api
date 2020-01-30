<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\BlockedPhoneNumber::class, function (Faker $faker) {
    return [
        'name'   => $faker->realText(20),
        'number' => substr($faker->e164PhoneNumber, -10)
    ];
});
