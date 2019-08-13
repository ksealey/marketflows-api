<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;
use App\Models\Campaign;

$factory->define(Campaign::class, function (Faker $faker) {
    return [
        'name' => 'MY CAMPAIGN ' . date('U') ,
        'type' => Campaign::TYPE_WEB,
        'starts_at' => date('Y-m-d H:i:s', strtotime('yesterday')),
        'ends_at' => date('Y-m-d H:i:s', strtotime('now + 2 days')),
        'activated_at' => date('Y-m-d H:i:s')
    ];
});
