<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\PaymentMethod::class, function (Faker $faker) {
    return [
        'stripe_id' => $faker->ein,
        'last_4'    => mt_rand(1111, 9999),
        'exp_month' => date('m'),
        'exp_year'  => date('Y', strtotime('now +2 years')),
        'type'      => 'credit',
        'brand'     => 'visa'
    ];
});
