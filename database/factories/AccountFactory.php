<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use App\Model;
use Faker\Generator as Faker;
use App\Models\Account;

$factory->define(Account::class, function (Faker $faker) {
    $accountTypes = Account::types();
    $accounType   = $accountTypes[mt_rand(0, count($accountTypes) - 1)];
    
    return [
        'name'                   => $faker->company,
        'account_type'           => $accounType,
        'default_tts_language'   => 'en-US',
        'default_tts_voice'      => 'Joanna'
    ];
});
