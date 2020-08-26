<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;
use App\Models\BillingStatement;

$factory->define(BillingStatement::class, function (Faker $faker) {
    return [
        'billing_period_starts_at' => now()->subDays(30)->startOfDay(), 
        'billing_period_ends_at'   => now()->subDays(1)->endOfDay(), 
    ];
});
