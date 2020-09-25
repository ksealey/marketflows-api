<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\BlockedCall::class, function (Faker $faker) {
    return [
        'created_at' => now()->format('Y-m-d H:i:s.u')
    ];
});
