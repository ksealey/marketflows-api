<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Agent::class, function (Faker $faker) {
    return [
        'role'          => 'GENERAL',
        'timezone'      => $faker->timezone,
        'first_name'    => $faker->firstName,
        'last_name'     => $faker->lastName,
        'email'         => $faker->unique()->safeEmail,
        'phone'         =>  substr($faker->e164PhoneNumber, -10),
        'password_hash' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'auth_token'    => str_random(128)
    ];
});
