<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(App\Models\Payment::class, function (Faker $faker) {
    return [
        'total' => mt_rand(10, 200) + 0.50,
        'external_id' => str_random(32)
    ];
});
