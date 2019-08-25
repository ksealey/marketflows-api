<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\UserInvite::class, function (Faker $faker) {
    return [
        'email'      => $faker->email,
        'key'        => str_random(40),
        'expires_at' => now()->addDays(30)
    ];
});
