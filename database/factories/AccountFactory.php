<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Account::class, function (Faker $faker) {
    return [
        'name'    => $faker->company,
        'country' => 'US'
    ];
});
