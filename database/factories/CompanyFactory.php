<?php

/* @var $factory \Illuminate\Database\Eloquent\Factory */

use Faker\Generator as Faker;

$factory->define(\App\Models\Company::class, function (Faker $faker) {
    return [
        'name'           => $faker->company,
        'industry'       => 'Manufacturing',
        'country'        => 'US',
        'tts_language'   => 'en-US',
        'tts_voice'      => 'Joanna',
        'ga_id'          => 'UA-147271162-1'
    ];
});
