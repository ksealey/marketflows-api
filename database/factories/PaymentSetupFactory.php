<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Auth\PaymentSetup::class, function (Faker $faker) {
    return [
        'customer_id' => str_random(20),
        'intent_id' => str_random(20),
        'intent_client_secret' => str_random(20),
        'expires_at' => now()->addHours(1)
    ];
});
