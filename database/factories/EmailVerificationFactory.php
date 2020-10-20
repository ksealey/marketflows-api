<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Auth\EmailVerification::class, function (Faker $faker) {
    return [
        'email'      => $faker->email,
        'code'       => mt_rand(100000,999999),
        'expires_at' => now()->addMinutes(30)
    ];
});
