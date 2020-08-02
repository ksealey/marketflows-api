<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\PaymentMethod::class, function (Faker $faker) {
    return [
        'external_id'    => 'tok_visa',
        'last_4'         => mt_rand(1111, 9999),
        'expiration'     => now()->addYears(2)->format('Y-m-d'),
        'brand'          => 'Visa',
        'type'           => 'credit',
        'primary_method' => 1,
        'last_used_at'   => now()->subDays(10),
    ];
});
