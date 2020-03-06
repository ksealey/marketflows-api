<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\Call::class, function (Faker $faker) {
    return [
        'external_id' => str_random(40),
        'direction'   => 'inbound',
        'status'      => 'completed',
        'duration'    => mt_rand(30, 500),
        'from_country_code' => 1,
        'from_number' => substr($faker->e164PhoneNumber, -10),
        'from_city'   => $faker->city,
        'from_state'  => $faker->stateAbbr,
        'from_zip'    => $faker->postcode,
        'from_country'=> 'US',
        'to_country_code' => 1,
        'to_city' => $faker->city,
        'to_state'=> $faker->stateAbbr,
        'to_zip'=> $faker->postcode,
        'to_country' => 'US'
    ];
});
