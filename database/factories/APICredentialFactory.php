<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\APICredential::class, function (Faker $faker) {
    return [
        'name'   => $faker->realText(64),
        'key'    => strtoupper(str_random(30)),
        'secret' => bcrypt(str_random(30))
    ];
});
