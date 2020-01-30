<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Account::class, function (Faker $faker) {
    return [
        'name'      => $faker->company,
        'balance'   => 0.00,
        'plan'      => 'BASIC',
        'auto_reload_enabled_at' => date('Y-m-d H:i:s'),
        'bill_at'   => now()->addMonths(2)
    ];
});
