<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;

$factory->define(\App\Models\Account::class, function (Faker $faker) {
    return [
        'name'                   => $faker->company,
        'balance'                => 0.00,
        'plan'                   => 'BASIC',
        'auto_reload_enabled_at' => date('Y-m-d H:i:s'),
        'auto_reload_minimum'    => 10,
        'auto_reload_amount'     => 20,
        'bill_at'                => now()->addMonths(2),
        'default_tts_language'   => 'en-US',
        'default_tts_voice'      => 'Joanna'
    ];
});
