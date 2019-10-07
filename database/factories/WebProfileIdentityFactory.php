<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\WebProfileIdentity::class, function (Faker $faker) {
    return [
        'uuid' => $faker->uuid(),
        'external_id' => $faker->ein(),
        'first_name' => $faker->firstName,
        'last_name'  => $faker->lastName,
        'email'      => $faker->email,
    ];
});
