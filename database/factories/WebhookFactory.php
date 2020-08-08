<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model;
use Faker\Generator as Faker;
use \App\Models\Company\Webhook;

$factory->define(Webhook::class, function (Faker $faker) {
    return [
        'action'    => Webhook::ACTION_CALL_END,
        'method'    => 'POST',
        'url'       => $faker->url,
        'enabled_at' => now()
    ];
});
