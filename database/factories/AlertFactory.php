<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Alert::class, function (Faker $faker) {
    return [
        'type' => \App\Models\Alert::TYPE_DEFAULT,
        'title' => $faker->realText(10),
        'message' => $faker->realText(150),
    ];
});
