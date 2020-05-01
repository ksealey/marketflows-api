<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;
use App\Models\Billing;

$factory->define(Billing::class, function (Faker $faker) {
    return [
        'period_starts_at' => now()->format('Y-m-d'), 
        'period_ends_at'   => now()->addMonths(1)->format('Y-m-d'),
        'bill_at'          => null,
        'last_billed_at'   => null,
        'attempts'         => 0,
        'locked_at'        => null
    ];
});
