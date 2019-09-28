<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;
use App\Models\Company\Campaign;

$factory->define(Campaign::class, function (Faker $faker) {
    return [
        'uuid'          => $faker->uuid(),
        'name'          => 'MY CAMPAIGN ' . mt_rand(999999, 999999999999),
        'type'          => Campaign::TYPE_WEB,
        'activated_at'  => date('Y-m-d H:i:s', strtotime('now - 10 minutes'))
    ];
});
