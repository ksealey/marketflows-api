<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\AccountBlockedPhoneNumber\AccountBlockedCall::class, function (Faker $faker) {
    return [
        'created_at' => now()
    ];
});
