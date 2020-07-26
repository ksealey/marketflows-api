<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;
use \App\Models\Account;

$factory->define(Account::class, function (Faker $faker) {
    return [
        'name'                   => $faker->company,
        'default_tts_language'   => 'en-US',
        'default_tts_voice'      => 'Joanna'
    ];
});
