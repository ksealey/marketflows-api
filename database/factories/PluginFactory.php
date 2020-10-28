<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Plugin::class, function (Faker $faker) {
    return [
        'key'           =>  str_random('10'),
        'name'          =>  $faker->company,
        'details'       => $faker->realText(100),
        'image_path'    =>$faker->imageUrl,
        'rules'         => json_encode([]),
        'billing_label' => $faker->realText(20),
        'price'         => number_format(mt_rand(0.99, 999.99), 2)
    ];
});
