<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Company\Contact::class, function (Faker $faker) {
    return [
        'create_method' => 'Incoming Call',
        'first_name'    => $faker->firstName,
        'last_name'     => $faker->lastName,
        'email'         => $faker->email,
        'country_code'  => '1',
        'number'        => substr($faker->e164PhoneNumber, -10),
        'city'          => $faker->city,
        'state'         => $faker->state,
        'zip'           => $faker->postcode,
        'country'       => 'US',
        'uuid'          => $faker->uuid
    ];
});
