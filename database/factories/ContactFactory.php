<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\Contact::class, function (Faker $faker) {
    return [
        'first_name' => $faker->firstName,
        'last_name'  => $faker->lastName,
        'email'      => $faker->email,
        'phone'      => substr($faker->e164PhoneNumber, -10),
        'alt_email'  => $faker->email,
        'alt_phone'  => substr($faker->e164PhoneNumber, -10),
        'city'       => $faker->city,
        'state'      => $faker->state,
        'zip'        => $faker->postcode,
        'country'    => 'US',
    ];
});
