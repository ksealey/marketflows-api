<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */
use App\Models\User;
use Illuminate\Support\Str;
use Faker\Generator as Faker;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(User::class, function (Faker $faker) {
    return [
        'first_name'            => $faker->firstName,
        'last_name'             => $faker->lastName,
        'email'                 => $faker->unique()->safeEmail,
        'phone'                 => substr($faker->e164PhoneNumber, -10),
        'password_hash'         => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
        'auth_token'            => str_random(128),
        'timezone'              => $faker->timezone,
        'role'                  => User::ROLE_ADMIN,
    ];
});
