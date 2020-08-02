<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;
use App\Models\Billing;

$factory->define(Billing::class, function (Faker $faker) {
    return [
        'external_id'              => str_random(40),
        'billing_period_starts_at' => now()->format('Y-m-d'), 
        'billing_period_ends_at'   => now()->addMonths(1)->format('Y-m-d'),
        'locked_at'                => null
    ];
});
