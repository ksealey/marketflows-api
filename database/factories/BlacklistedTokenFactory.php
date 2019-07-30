<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Auth\BlacklistedToken::class, function (Faker $faker) {
    return [
        'remove_at' => date('Y-m-d H:i:s', strtotime('now +30 days'))
    ];
});
